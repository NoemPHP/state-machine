<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

use Noem\State\Feature\Message\Message;

/**
 * Base class for all interaction responses
 *
 * Interaction responses are messages sent from external agents/frameworks
 * back to state machines in response to InteractionRequests. They extend
 * Message to leverage correlation-based request-response infrastructure.
 */
abstract class InteractionResponse extends Message
{
    public function __construct(
        public readonly mixed $value,
        public readonly bool $cancelled = false,
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }
}
