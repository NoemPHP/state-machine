<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec Framework adapters emit responses immediately after collection for low latency
 * @see specs/features/interaction.yaml - framework-adapter-response-creation
 */
class AdapterEmitsImmediatelyTest extends TestCase
{
    public function testAdapterEmitsImmediately(): void
    {
        $request = new ConfirmRequest(question: 'Test?');
        $responseTime = null;

        $request->then(function ($response) use (&$responseTime) {
            $responseTime = microtime(true);
        });

        $startTime = microtime(true);

        // Adapter emits response immediately (no batching)
        $response = new ConfirmResponse(confirmed: true, correlationId: $request->correlationId());
        $request->deliverResponse($response);

        $this->assertNotNull($responseTime);
        $this->assertLessThan(0.1, $responseTime - $startTime, 'Response should be emitted immediately');
    }
}
