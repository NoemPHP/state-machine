<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\Region;

class ExtendedState
{
    public bool $pristine = true;

    public function __construct(
        public Region $region,
        /**
         * Target the data of an individual state.
         * If unset, target region-wide data shared with all states
         *
         * @var string|null
         */
        public ?string $state = null
    ) {
    }
}
