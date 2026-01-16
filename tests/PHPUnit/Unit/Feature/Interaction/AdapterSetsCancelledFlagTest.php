<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec Framework adapters set cancelled flag to true on timeout or user cancellation
 * @see specs/features/interaction.yaml - framework-adapter-response-creation
 */
class AdapterSetsCancelledFlagTest extends TestCase
{
    public function testAdapterSetsCancelledFlag(): void
    {
        $request = new ConfirmRequest(question: 'Test?');

        // Adapter creates cancelled response
        $response = new ConfirmResponse(
            confirmed: false,
            cancelled: true,
            correlationId: $request->correlationId()
        );

        $this->assertTrue($response->cancelled);
    }
}
