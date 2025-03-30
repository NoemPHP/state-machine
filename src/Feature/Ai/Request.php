<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai;

use Noem\Agent\ResponseFormat;

class Request
{

    public function __construct(
        public readonly string $baseUrl,
        public readonly string $token,
        public readonly string $model,
        public readonly string $prompt,
        public readonly int $maxTokens,
        public readonly float $temperature,
        public readonly ?string $stop,
        public readonly ?ResponseFormat $responseFormat = null,
        public readonly bool $logprobs = true,
        public readonly bool $stream = false
    ) {
    }
}
