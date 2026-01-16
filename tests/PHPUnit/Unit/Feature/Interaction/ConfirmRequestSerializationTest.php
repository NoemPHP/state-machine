<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use PHPUnit\Framework\TestCase;

/**
 * @spec ConfirmRequest.jsonSerialize() includes question, defaultValue, context, and timeoutMs in data field
 * @see specs/features/interaction.yaml - json-serialization-requests
 */
class ConfirmRequestSerializationTest extends TestCase
{
    public function testSerializesAllConfirmProperties(): void
    {
        $request = new ConfirmRequest(
            question: 'Deploy to production?',
            defaultValue: true,
            context: 'Critical deployment',
            timeoutMs: 30000
        );

        $json = $request->jsonSerialize();

        $this->assertSame('Deploy to production?', $json['data']['question']);
        $this->assertTrue($json['data']['defaultValue']);
        $this->assertSame('Critical deployment', $json['data']['context']);
        $this->assertSame(30000, $json['data']['timeoutMs']);
    }
}
