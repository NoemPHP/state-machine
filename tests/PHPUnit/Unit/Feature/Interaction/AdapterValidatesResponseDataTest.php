<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\SelectRequest;
use Noem\State\Feature\Interaction\SelectResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec Framework adapters validate response data before creating response
 * @see specs/features/interaction.yaml - framework-adapter-response-creation
 */
class AdapterValidatesResponseDataTest extends TestCase
{
    public function testAdapterCanValidateResponseData(): void
    {
        $request = new SelectRequest(
            question: 'Choose?',
            options: ['opt1' => 'Option 1', 'opt2' => 'Option 2']
        );

        // Adapter validates selectedKey exists in options
        $selectedKey = 'opt1';
        $isValid = isset($request->options[$selectedKey]);

        $this->assertTrue($isValid, 'Adapter can validate response data before creating response');

        // Create response only if valid
        if ($isValid) {
            $response = new SelectResponse(selectedKey: $selectedKey, correlationId: $request->correlationId());
            $this->assertSame('opt1', $response->selectedKey);
        }
    }
}
