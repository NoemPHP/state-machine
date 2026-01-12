<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\RegionBuilder;

/**
 * Parameters for BuilderMethodCall chain
 *
 * @property-read RegionBuilder $builder The builder instance
 * @property-read string $method The method name being called
 * @property-read array $arguments The method arguments
 */
final readonly class BuilderMethodCallParams
{
    public function __construct(
        public RegionBuilder $builder,
        public string $method,
        public array $arguments
    ) {
    }
}
