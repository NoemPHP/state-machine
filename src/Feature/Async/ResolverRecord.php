<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

use Noem\State\Region;

class ResolverRecord
{
    public function __construct(
        public readonly Region $region,
        public readonly string $key,
        public readonly \Closure $resolver,
        public readonly ?AsyncConfig $asyncConfig = null
    ) {
    }
}
