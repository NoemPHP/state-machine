<?php

declare(strict_types=1);

namespace Noem\State\Feature\ExtendedState;

use Noem\State\Chains;
use Noem\State\Chains\Params;
use Noem\State\Chains\Params\Connection;
use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;
use Noem\State\Region;

class ExtendedState implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use(function (
            Chains\Get $getChain,
            Chains\Set $setChain,
            Chains\Meta $meta,
            Chains\ExtendedState $extendedState,
            Chains\ConnectedRegions $connectedRegions,
            Chains\InvokeCallback $invokeCallback,
        ) {
            $setChain->link(
                function (Params\Set $set, callable $next) use ($getChain, $meta, $connectedRegions) {
                    $data = $meta->call($set->region);
                    $data[$set->key] = $set->value;

                    return $next($set);
                }
            );

            $getChain->link(
                function (Params\Get $get, callable $next) use ($getChain, $meta) {
                    $data = $meta->call($get->region);
                    if ($data->offsetExists($get->key)) {
                        return $data[$get->key];
                    }

                    return $next($get);
                }
            );

            $contextStorage = new \SplObjectStorage();

            $inheritContextFromRegions = function (Region $remoteRegion) use ($connectedRegions) {
                $context = new Connection($remoteRegion, false, 0);

                return $connectedRegions->call($context);
            };
            $bubbleContextFromRegion = function (Region $remoteRegion) use ($connectedRegions) {
                $context = new Connection($remoteRegion, true, 0);

                return $connectedRegions->call($context);
            };
            /**
             * TODO This needs a safer data structure. Maybe something with WeakMaps?
             */
            $views = [];
            /**
             * Set up shared state storage for connected regions
             */
            //$extendedState
            //    /**
            //     * Find all connected Regions the current Region inherits from.
            //     * Then we create a new Data object that acts as a view on the real ones
            //     * This means that connected Regions receive a shared state that is completely transparent
            //     */
            //    ->link(
            //        function (
            //            Params\ExtendedState $ctx,
            //            callable $next,
            //            callable $first
            //        ) use (
            //            $inheritContextFromRegions,
            //            &$views
            //        ): Mesh {
            //            $inheritFrom = $inheritContextFromRegions($ctx->region);
            //            if (empty($inheritFrom)) {
            //                return $next($ctx);
            //            }
            //            $uniqueId = array_reduce(
            //                $inheritFrom,
            //                fn($carry, $originRegion): int => $carry + spl_object_id($originRegion),
            //                0
            //            );
            //            if (!array_key_exists($uniqueId, $views)) {
            //                $data = new Mesh();
            //                foreach ($inheritFrom as $originRegion) {
            //                    $ctx->region = $originRegion;
            //                    $data->extend($first($ctx));
            //                }
            //                $views[$uniqueId] = $data;
            //            }
            //
            //            return $views[$uniqueId];
            //        }
            //    )
            //    /**
            //     * The same key/value-pair of Regions can always yield the dame compound Data view.
            //     * The Data object will ensure any read/write accesses propagate to the correct Region storage
            //     */
            //    ->memoize(
            //        fn(Params\ExtendedState $a, Params\ExtendedState $b) => $a->region === $b->region
            //    );

            /**
             * Set up a cached state provider that will be used to store the state of each region.
             */
            //$extendedState->link(
            //    function (
            //        Context\ExtendedStateContext $ctx,
            //        callable $next,
            //        callable $first
            //    ) use (
            //        $stateCache,
            //        $regionCache
            //    ) {
            //        $cache = is_null($ctx->access->state)
            //            ? $regionCache
            //            : $stateCache;
            //        $cached = $cache->contains($ctx->access->region)
            //            ? $cache[$ctx->access->region]
            //            : [];
            //        $ctx->data = is_null($ctx->data)
            //            ? $cached
            //            : array_merge($ctx->data, $cached);
            //        $data = $next($ctx);
            //        $cache[$ctx->access->region] = $data->data;
            //
            //        return $data;
            //    }
            //);
            //$this->data = $extendedState->withProvider($stateProvider);
            //$this->set = $setContext->withProvider(function (Params\Set $ctx): bool {
            //    $extendedStateContext = new Params\ExtendedState($ctx->region);
            //    $data = ($this->data)->call($extendedStateContext);
            //    $data[$ctx->key] = $ctx->value;
            //
            //    return true;
            //});
            //$this->get = $getContext->withProvider(function (Params\Get $ctx): mixed {
            //    $extendedStateContext = new Params\ExtendedState($ctx->region);
            //    $data = ($this->data)->call($extendedStateContext);
            //
            //    return $data[$ctx->key];
            //});
            /**
             * Bind each callback that passes through to an instance of Bound
             * This enables all callback handlers to access extended state using '$this->thing'
             * and call methods using $this->doSomething()
             */
            $invokeCallback->link(
                function (Params\Callback $callback, callable $next) use ($contextStorage, $getChain, $setChain) {
                    $closure = ($callback->handler)(...);
                    $region = $callback->region;
                    if (!$contextStorage->contains($region)) {
                        $contextStorage[$region] = new Bound($region, $getChain, $setChain);
                    }
                    $callback->handler = $closure->bindTo($contextStorage[$region]);

                    return $next($callback);
                }
            );
        });
    }
}
