<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

use Noem\State\Feature\Message\Message;

/**
 * Base class for all interaction requests
 *
 * Interaction requests are messages sent from state machines to external
 * agents/frameworks requesting information or decisions. They extend Message
 * to leverage correlation-based request-response infrastructure.
 */
abstract class InteractionRequest extends Message
{
    public function __construct(
        public readonly string $question,
        public readonly ?string $context = null,
        public readonly ?int $timeoutMs = null,
        public readonly ?string $interactionId = null,
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }

    /**
     * Get interaction type identifier
     *
     * Returns a string identifying the type of interaction (e.g., 'confirm', 'select').
     * Used by framework adapters to route to appropriate handlers.
     *
     * @return string Type identifier ('confirm', 'select', 'choice', 'prompt', etc.)
     */
    abstract public function getType(): string;
}
