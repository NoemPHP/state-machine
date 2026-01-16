<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionRequest.jsonSerialize() includes type field with fully qualified class name
 * @see specs/features/interaction.yaml - json-serialization-requests
 */
class RequestSerializesTypeTest extends TestCase
{
    public function testSerializesTypeFQCN(): void
    {
        $request = new ConfirmRequest(question: 'Test?');
        $json = $request->jsonSerialize();

        $this->assertArrayHasKey('type', $json);
        $this->assertSame(ConfirmRequest::class, $json['type']);
    }
}
