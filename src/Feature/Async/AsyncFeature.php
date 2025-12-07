<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Noem\State\Chains;
use Noem\State\Chains\Params;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\Loader\LoaderChains;
use Noem\State\Feature\Async\AsyncChains\Params as AsyncParams;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Middleware\ChainException;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;
use Noem\State\Region;
use Noem\State\RegionBuilder;

class AsyncFeature implements Feature
{
    private \SplObjectStorage $coroutinesByRegion;

    /**
     * A map to connect Tasks and their originating callback
     * (the one that returns the Generator backing the task)
     *
     * @var \WeakMap<callable,Task>
     */
    private \WeakMap $callbackTaskMap;

    public function __construct()
    {
        $this->coroutinesByRegion = new \SplObjectStorage();
        $this->callbackTaskMap = new \WeakMap();
    }

    /**
     * @throws ChainException
     */
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(
            fn(): AsyncChains\Enqueue => new AsyncChains\Enqueue($this->coroutinesByRegion),
            fn(): Resolvers => new Resolvers()
        );
        $chainMail
            ->use($this->deferTicksUntilActionComplete(...))
            ->use($this->enqueueCoroutines(...))
            ->use($this->extendBuilderSchema(...))
            ->use($this->processBuilderConfig(...))
            ->use($this->triggerResolversOnMetadataAccess(...))
            ->use($this->setupAsyncContextMethods(...));
    }

    /**
     * Defers ticking until all action callbacks have been processed.
     * This ensures that all async tasks progress exactly once per trigger,
     * maintaining fairness when multiple async callbacks are registered.
     */
    private function deferTicksUntilActionComplete(
        ?Chains\DispatchAction $dispatchAction,
    ): void {
        if (!$dispatchAction) {
            return;
        }

        $dispatchAction->link(function (Params\Action $context, callable $next) {
            $region = $context->region;

            // Ensure scheduler exists
            $scheduler = $this->getCoroutineSchedulerForRegion($this->coroutinesByRegion, $region);

            // Clean up finished tasks BEFORE processing callbacks
            // This ensures callbacks that finished in the previous trigger are removed from the map
            // Collect finished tasks first to avoid modification during iteration
            $finishedCallbacks = [];
            foreach ($this->callbackTaskMap as $callback => $task) {
                if ($task->isFinished()) {
                    $finishedCallbacks[] = $callback;
                }
            }
            // Now remove them
            foreach ($finishedCallbacks as $callback) {
                unset($this->callbackTaskMap[$callback]);
            }

            // Process all callbacks
            $result = $next($context);

            // Tick once after all callbacks have been processed
            $scheduler->tick();

            return $result;
        });
    }

    private function enqueueCoroutines(
        Chains\InvokeCallback $invokeCallback,
        AsyncChains\Enqueue $enqueue,
    ): void {
        /**
         * Modify the invoke callback to handle asynchronous callbacks
         */
        $invokeCallback->link(
            function (
                Params\Callback $context,
                callable $next,
                callable $first
            ) use (
                $enqueue
            ): mixed {
                if (!$this->coroutinesByRegion->contains($context->region)) {
                    $this->coroutinesByRegion->attach($context->region, new CoroutineScheduler());
                }
                $coroutines = $this->coroutinesByRegion[$context->region];
                assert($coroutines instanceof CoroutineScheduler);

                /**
                 * If we already track a Task, check if it's finished.
                 * If finished, remove from map and fall through to create new task.
                 * Otherwise, return last yielded value without ticking (deferred tick will handle it).
                 */
                if ($this->callbackTaskMap->offsetExists($context->handler)) {
                    $task = $this->callbackTaskMap[$context->handler];

                    // Check if task is already finished (from previous trigger)
                    if ($task->isFinished()) {
                        // Task completed, remove from map manually
                        // (scheduler will clean up the task during its next tick)
                        unset($this->callbackTaskMap[$context->handler]);
                        // Now fall through to create new task
                    } else {
                        // Task still running, return last yielded value
                        // The deferred tick in deferTicksUntilActionComplete will advance it
                        return $coroutines->getLastYielded($task);
                    }
                }

                /**
                 * Either no task exists yet, or the previous task completed.
                 * Invoke callback to get result (either sync value or new generator)
                 */
                $result = $next($context);
                if (!$result instanceof \Generator) {
                    /**
                     * Oh, it's a normal synchronous closure, then just move on
                     * The deferred tick will still run to progress any other async tasks
                     */
                    return $result;
                }

                $task = $enqueue->call(new AsyncParams\EnqueueParams($context->region, $result));
                $this->callbackTaskMap[$context->handler] = $task;

                // Don't tick here - the deferred tick in deferTicksUntilActionComplete will handle it
                return $coroutines->getLastYielded($task);
            }
        );
    }

    /**
     * If the RegionLoader is used, extend its schema so that it accepts a list of resolvers.
     *
     * @param LoaderChains\Schema|null $schema
     *
     * @return void
     * @see RegionLoader
     */
    private function extendBuilderSchema(
        ?LoaderChains\Schema $schema,
    ): void {
        $schema?->link(function (SchemaContext $context, callable $next) {
            $contextSchema = $context->getCustomSchema('context');
            if (!$contextSchema) {
                return $next($context);
            }
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
    }

    private function processBuilderConfig(
        Chains\EnhanceRegionBuilder $enhanceRegionBuilder,
        Resolvers $resolvers,
    ): void {
        $enhanceRegionBuilder->link(function (Params\BuildParams $context, callable $next) use ($resolvers) {
            $builder = $next($context);

            $asyncConfig = $context->config(Config\AsyncConfig::class);
            if (!$asyncConfig->hasResolvers()) {
                return $builder;
            }

            assert($builder instanceof RegionBuilder);
            foreach ($asyncConfig->resolvers() as $resolver) {
                $builder->addBuildStep(
                    new AddResolver($resolver['name'], $resolver['run'])
                );
            }

            return $builder;
        });
    }

    private function triggerResolversOnMetadataAccess(
        Chains\Meta $meta,
        Chains\PrepareInvokable $prepareInvokable,
        Chains\InvokeCallback $invokeCallback,
        AsyncChains\Enqueue $enqueue,
        Resolvers $resolvers,
    ): void {
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
                $prepareInvokable,
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
                    $this->coroutinesByRegion,
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
                            $prepareInvokable,
                            $invokeCallback,
                            $enqueue,
                        ) {
                            $wrapper = function () use (
                                $resolver,
                                $r,
                                $prepareInvokable,
                                $invokeCallback,
                                $scheduler
                            ) {
                                $callbackParams = new Params\Callback(
                                    $resolver->region,
                                    $resolver->resolver,
                                    $r
                                );
                                /**
                                 * Run any middleware that modifies callback functions
                                 */
                                $callback = $prepareInvokable->call($callbackParams);

                                /**
                                 * Construct a new callback with the modified function
                                 */
                                $callbackParams = new Params\Callback(
                                    $resolver->region,
                                    $callback,
                                    $r
                                );
                                /**
                                 * Now actually execute it via another chain.
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
    }

    private function setupAsyncContextMethods(
        ?BoundAccess $boundAccess,
        ?Chains\Get $getChain,
        ?Chains\Set $setChain,
    ): void {
        if (empty(array_filter(func_get_args()))) {
            return;
        }
        $boundAccess?->link(function (BoundAccessParams $params, callable $next) use ($getChain) {
            if ($params->type !== BoundAccessParams::TYPE_METHOD) {
                return $next($params);
            }
            switch ($params->name) {
                //case 'set':
                //    $args = $params->payload;
                //    $key = array_shift($args);
                //    $value = array_shift($args);
                //    $setChain->call(
                //        new Params\Set(
                //            $params->region,
                //            $key,
                //            $value
                //        )
                //    );
                //
                //    return null;
                case 'getAsync':
                    $args = $params->payload;
                    $key = array_shift($args);
                    $result = $getChain->call(
                        new Params\Get(
                            $params->region,
                            $key,
                        )
                    );

                    return Call::call(function () {
                    });
                //case 'dispatch':
                //    $args = $params->payload;
                //    $trigger = array_shift($args);
                //    $params->region->trigger($trigger, true);
                //
                //    return null;

                default:
                    return $next($params);
            }
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
