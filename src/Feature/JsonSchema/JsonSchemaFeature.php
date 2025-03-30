<?php

declare(strict_types=1);

namespace Noem\State\Feature\JsonSchema;

use Nette\Schema\Elements\Type;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Context\SchemaContext;
use Noem\State\Feature\OrthogonalRegions\RegionChains\ParentRegion;
use Noem\State\Middleware\ChainMail;
use Noem\State\Feature\Loader\LoaderChains;

class JsonSchemaFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(
        )->use(
            function (
                ?LoaderChains\Schema $schema,
            ) {
                /**
                 * Extend the region schema to support the 'regions' item within a state config
                 */
                $schema?->link(function (SchemaContext $context, callable $next) {
                    $nestedRegion = new Type('list');
                    $context->state->extend([
                        'context' => $nestedRegion,
                    ]);
                    $nestedRegion->items($context->region);
                    $next($context);
                });
            }
        );
    }
}
