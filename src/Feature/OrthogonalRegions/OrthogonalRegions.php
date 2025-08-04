<?php

declare(strict_types=1);

namespace Noem\State\Feature\OrthogonalRegions;

use Nette\Schema\Elements\Type;
use Nette\Schema\Expect;
use Noem\State\Chains;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\OrthogonalRegions\RegionChains\ParentRegion;
use Noem\State\Middleware\ChainMail;

class OrthogonalRegions implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(
            fn(Chains\ConnectedRegions $connectedRegions): ParentRegion => new ParentRegion($connectedRegions),
        )->use(
            function (
                ?LoaderChains\Schema $schema,
                Chains\ConnectedRegions $connectedRegions,
                Chains\Get $get,
                Chains\Set $set,
                ParentRegion $parentRegion
            ) {
                /**
                 * Extend the region schema to support the 'regions' item within a state config
                 */
                $schema?->link(function (SchemaContext $context, callable $next) {
                    $nestedRegion = new Type('list');
                    /**
                     * The extend() method produces a new object
                     */
                    $context->state = $context->state->extend([
                        'regions' => $nestedRegion,
                    ]);
                    /**
                     * Therefore we need to update the existing reference to the states definition
                     */
                    $context->region = $context->region->extend([
                        'states' => Expect::listOf($context->state),
                    ]);
                    $nestedRegion->items($context->region);

                    return $next($context);
                });
                //$get->link(
                //    function (Chains\Context\GetContext $context, callable $next, callable $first) use ($parentRegion) {
                //        $parentRegion = $parentRegion->of($context->region);
                //        if (!is_null($parentRegion)) {
                //            $context->region = $parentRegion;
                //        }
                //        return $next($context);
                //    }
                //);
                //$set->link(
                //    function (Chains\Context\SetContext $context, callable $next, callable $first) use ($parentRegion) {
                //        $parentRegion = $parentRegion->of($context->region);
                //        if (!is_null($parentRegion)) {
                //            $context->region = $parentRegion;
                //        }
                //        return $next($context);
                //    }
                //);
                //$connectedRegions->link(function (Chains\Params\Connection $context, callable $next) {
                //    return $next($context);
                //});
            }
        );
    }
}
