<?php

declare(strict_types=1);

namespace Noem\State\Feature\OrthogonalRegions;

use Nette\Schema\Elements\Type;
use Noem\State\Chains;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains;
use Noem\State\Feature\Loader\LoaderChains\Context\SchemaContext;
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
                    $context->state->extend([
                        'regions' => $nestedRegion,
                    ]);
                    $nestedRegion->items($context->region);
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
