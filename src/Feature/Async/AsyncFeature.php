<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Noem\State\Chains;
use Noem\State\Chains\Params;
use Noem\State\Feature\Loader\LoaderChains;
use Noem\State\Feature\Async\AsyncChains\Params as AsyncParams;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Context\SchemaContext;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;
use Noem\State\Region;
use Noem\State\RegionBuilder;

class AsyncFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        /**
         * Implement coroutine scheduler
         */
        $coroutinesByRegion = new \SplObjectStorage();
        $chainMail->supply(
            fn(): AsyncChains\Enqueue => new AsyncChains\Enqueue($coroutinesByRegion),
            fn(): Resolvers => new Resolvers()
        );
        $chainMail->use(function (
            Chains\InvokeCallback $invokeCallback,
            Chains\Meta $meta,
            AsyncChains\Enqueue $enqueue,
            Resolvers $resolvers,
            Chains\Get $getChain,
            ?LoaderChains\Schema $schema,
            ?LoaderChains\Loader $loader
        ) use ($coroutinesByRegion): void {
            /**
             * A map to connect Tasks and their originating callback
             * (the one that returns the Generator backing the task)
             *
             * @param \SplObjectStorage<callable,Task>
             */
            $callbackTaskMap = new \WeakMap();
            /**
             * Modify the invoke callback to handle asynchronous callbacks
             */
            $invokeCallback->link(
                function (
                    Params\Callback $context,
                    callable $next,
                    callable $first
                ) use (
                    $coroutinesByRegion,
                    $callbackTaskMap,
                    $enqueue
                ): mixed {
                    /**
                     * We always need the callback first
                     */
                    $result = $next($context);
                    if (!$coroutinesByRegion->contains($context->region)) {
                        $coroutinesByRegion->attach($context->region, new CoroutineScheduler());
                    }
                    $coroutines = $coroutinesByRegion[$context->region];
                    assert($coroutines instanceof CoroutineScheduler);
                    /**
                     * If we already track a Task, then just kick the machine.
                     */
                    if ($callbackTaskMap->offsetExists($context->handler)) {
                        $coroutines->tick();

                        return $coroutines->getLastYielded($callbackTaskMap[$context->handler]);
                    }

                    if (!$result instanceof \Generator) {
                        $coroutines->tick();

                        return $result;
                    }

                    $task = $enqueue->call(new AsyncParams\EnqueueParams($context->region, $result));
                    $callbackTaskMap[$context->handler] = $task;
                    $coroutines->tick();

                    return $coroutines->getLastYielded($task);
                }
            );
            /**
             * Trigger resolvers when corresponding meta data is accessed
             */
            $observers = new \SplObjectStorage();
            $meta->link(
                function (
                    Params\Meta $metaParams,
                    callable $next
                ) use (
                    $observers,
                    $coroutinesByRegion,
                    $callbackTaskMap,
                    $invokeCallback,
                    $enqueue,
                    $resolvers
                ): Mesh {
                    $mesh = $next($metaParams);
                    assert($mesh instanceof Mesh);
                    /**
                     * We are only interested in the context metadata
                     */
                    if (!$metaParams->type->is(ContextMetaType::class)) {
                        return $mesh;
                    }
                    /**
                     * Observers already set up? Then we can bail
                     * TODO Maybe this should be scoped by Mesh and not Region?
                     * A region could be part of multiple shared meshes and resolvers
                     * might yield different results based on their environment
                     */
                    if ($observers->contains($metaParams->region)) {
                        return $mesh;
                    }
                    $ornaments = [];
                    $resolvers = $resolvers->getResolversForRegion($metaParams->region);
                    $scheduler = $this->getCoroutineSchedulerForRegion(
                        $coroutinesByRegion,
                        $metaParams->region
                    );
                    foreach ($resolvers as $offset => $resolver) {
                        $ornaments[] = new Ornament(
                            $mesh,
                            $offset,
                            function (OrnamentResolver $r) use (
                                $resolvers,
                                $resolver,
                                $scheduler,
                                $invokeCallback,
                                $enqueue,
                                $callbackTaskMap
                            ) {
                                $wrapper = function () use ($resolver, $r, $invokeCallback, $scheduler) {
                                    $callbackParams = new Params\Callback(
                                        $resolver->region,
                                        $resolver->resolver,
                                        $r
                                    );

                                    /**
                                     * Our own middleware in the InvokeCallback chain will enqueue
                                     * and track the resolver from now on
                                     */
                                    $invokeCallback->call($callbackParams);
                                    $task = $scheduler->getLastAddedTask();
                                    $hasFinished = false;
                                    $scheduler->onComplete($task, function () use (&$hasFinished) {
                                        $hasFinished = true;
                                    });
                                    while (!$hasFinished) {
                                        yield;
                                    }

                                    return $task->getReturn();
                                };
                                $task = $enqueue->call(new AsyncParams\EnqueueParams($resolver->region, $wrapper()));
                                $scheduler->onComplete($task, function () use ($r, $task) {
                                    $r->resolve($task->getReturn());
                                });
                            }
                        );
                    }
                    $observers[$metaParams->region] = $ornaments;

                    return $mesh;
                }
            );
            $schema?->link(function (SchemaContext $context, callable $next) {
                $contextSchema = $context->getCustomSchema('context');
                assert($contextSchema instanceof Structure);
                $contextSchema = $contextSchema->extend([
                    'resolvers' => Expect::listOf(
                        Expect::structure(
                            [
                                'name' => Expect::string(),
                                'run' => $context->callback,
                            ]
                        )
                    ),
                ]);
                $context->addCustomSchema('context', $contextSchema);
                /**
                 * Update the reference on the region schema since we just produced a new object
                 */
                $context->region = $context->region->extend([
                    'context' => $contextSchema,
                ]);

                return $next($context);
            });
            $loader?->link(function (LoaderChains\Context\LoaderContext $context, callable $next) use ($resolvers) {
                $data = $context->data;
                if (!isset($data['context']['resolvers'])) {
                    return $next($context);
                }
                $builder = $next($context);
                assert($builder instanceof RegionBuilder);
                $builder->addStep(function (RegionBuilder $builder, callable $next) use ($data, $resolvers) {
                    $region = $next($builder);
                    foreach ($data['context']['resolvers'] as $resolver) {
                        $resolvers->addResolver(new ResolverRecord($region, $resolver['name'], $resolver['run']));
                    }

                    return $region;
                });

                return $builder;
            });
        });
    }

    private function getCoroutineSchedulerForRegion(
        \SplObjectStorage $coroutinesByRegion,
        Region $region
    ): CoroutineScheduler {
        if (!$coroutinesByRegion->contains($region)) {
            $coroutinesByRegion->attach($region, new CoroutineScheduler());
        }

        return $coroutinesByRegion[$region];
    }

    private function enqueueForRegion(
        \SplObjectStorage $coroutinesByRegion,
        Region $region,
        \Generator $coroutine,
        int $id
    ): void {
        $this->getCoroutineSchedulerForRegion($coroutinesByRegion, $region)->enqueue($coroutine, $id);
    }
}
