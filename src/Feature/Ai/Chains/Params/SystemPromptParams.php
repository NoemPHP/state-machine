<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai\Chains\Params;

class SystemPromptParams
{

    public function __construct(
        public readonly string $persona
    ) {
    }
}
