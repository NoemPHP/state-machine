<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

/**
 * Exception thrown when interaction is cancelled or times out
 *
 * Thrown by InteractionFeature when:
 * - User cancels the interaction
 * - Interaction times out
 * - No response received before timeout
 */
class InteractionCancelledException extends \RuntimeException
{
}
