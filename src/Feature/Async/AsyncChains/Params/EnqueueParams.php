<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async\AsyncChains\Params;

use Noem\State\Region;

class EnqueueParams
{

    public function __construct(
        public readonly Region $region,
        public readonly \Generator $coroutine
    ) {
    }
}
