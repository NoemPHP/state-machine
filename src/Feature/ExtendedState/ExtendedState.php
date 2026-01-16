<?php

declare(strict_types=1);

namespace Noem\State\Feature\ExtendedState;

use Nette\Schema\Expect;
use Noem\State\Chains;
use Noem\State\Chains\Params;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Middleware\ChainMail;
use Noem\State\Feature\Loader\LoaderChains;

class ExtendedState implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(fn(): BoundAccess => new BoundAccess());
        $chainMail->use(function (
            Chains\Get $getChain,
            Chains\Set $setChain,
            Chains\Meta $meta,
            Chains\ExtendedState $extendedState,
            Chains\ConnectedRegions $connectedRegions,
            Chains\PrepareInvokable $prepareInvokable,
            BoundAccess $boundAccess,
            ?LoaderChains\Schema $schema,
        ) {
            $setChain->link(
                function (Params\Set $set, callable $next) use ($getChain, $meta, $connectedRegions) {
                    $metaParams = new Params\Meta($set->region, ContextMetaType::get());
                    $data = $meta->call($metaParams);
                    $data[$set->key] = $set->value;

                    return $next($set);
                }
            );

            $getChain->link(
                function (Params\Get $get, callable $next) use ($getChain, $meta) {
                    $regionId = spl_object_id($get->region);
                    $metaParams = new Params\Meta($get->region, ContextMetaType::get());
                    $data = $meta->call($metaParams);
                    $objectId = spl_object_id($meta);
                    if (isset($data[$get->key])) {
                        return $data[$get->key];
                    }

                    return $next($get);
                }
            );

            $contextStorage = new \SplObjectStorage();
            /**
             * Bind each callback that passes through to an instance of Bound
             * This enables all callback handlers to access extended state using '$this->thing'
             * and call methods using $this->doSomething()
             */
            $boundCallbackMap = new \SplObjectStorage();
            $prepareInvokable->link(
                function (
                    Params\Callback $callback,
                    callable $next
                ) use (
                    $boundCallbackMap,
                    $contextStorage,
                    $boundAccess
                ) {
                    $id = spl_object_id($callback->handler);
                    if (!$boundCallbackMap->offsetExists($callback->handler)) {
                        $closure = ($callback->handler)(...);
                        $region = $callback->region;
                        if (!$contextStorage->offsetExists($region)) {
                            $contextStorage[$region] = new Bound($region, $boundAccess);
                        }
                        $bound = $closure->bindTo($contextStorage[$region], $contextStorage[$region]);
                        if ($bound === null) {
                            throw new \RuntimeException(
                                'Failed to bind closure to Bound context. ' .
                                'This may happen with static closures or closures that cannot be rebound.'
                            );
                        }
                        $boundCallbackMap->offsetSet($callback->handler, $bound);
                    }

                    $callback->handler = $boundCallbackMap->offsetGet($callback->handler);

                    return $next($callback);
                }
            );
            $schema?->link(function (SchemaContext $context, callable $next) {
                $contextSchema = Expect::structure([]);
                $context->addCustomSchema('context', $contextSchema);
                $context->region = $context->region->extend([
                    'context' => $contextSchema,
                ]);

                return $next($context);
            }, prepend: true); // Prepend to run FIRST - creates base schema before other features extend it

            /**
             * Configure default bound access for 'region' property
             */
            $boundAccess->link(function (BoundAccessParams $params, callable $next) {
                if ($params->type === BoundAccessParams::TYPE_PROPERTY && $params->name === 'region') {
                    return $params->region;
                }
                return $next($params);
            });

            /**
             * Configure default bound access getters/setters
             */
            $boundAccess->link(function (BoundAccessParams $params, callable $next) use ($setChain, $getChain) {
                if ($params->type !== BoundAccessParams::TYPE_METHOD) {
                    return $next($params);
                }
                switch ($params->name) {
                    case 'set':
                        $args = $params->payload;
                        $key = array_shift($args);
                        $value = array_shift($args);
                        $setChain->call(
                            new Params\Set(
                                $params->region,
                                $key,
                                $value
                            )
                        );

                        return null;
                    case 'get':
                        $args = $params->payload;
                        $key = array_shift($args);
                        $result = $getChain->call(
                            new Params\Get(
                                $params->region,
                                $key,
                            )
                        );

                        return $result;
                    case 'dispatch':
                        $args = $params->payload;
                        $trigger = array_shift($args);
                        $params->region->trigger($trigger, true);

                        return null;

                    default:
                        return $next($params);
                }
            });
        });
    }
}
