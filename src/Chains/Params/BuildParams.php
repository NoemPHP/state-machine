<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\Middleware\Mesh;
use Noem\State\RegionBuilder;

class BuildParams extends Mesh
{
    public function __construct(
        public readonly RegionBuilder $builder,
        private ?iterable &$params = [],
    ) {
        parent::__construct($params);
    }
}
