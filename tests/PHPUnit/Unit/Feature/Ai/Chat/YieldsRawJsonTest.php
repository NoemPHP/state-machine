<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat yields raw JSON responses when asText is false
 */
#[Group('ai'), Group('chat-api')]
class YieldsRawJsonTest extends TestCase
{
    #[Test]
    public function yieldsRawJsonTest(): void
    {
        $chunk1 = ['choices' => [['message' => ['content' => 'Hello', 'role' => 'assistant']]]];
        $chunk2 = ['choices' => [['message' => ['content' => ' world']]]];

        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend->method('stream')
            ->willReturn((function() use ($chunk1, $chunk2) {
                yield $chunk1;
                yield $chunk2;
            })());

        $chat = new \Noem\State\Feature\Ai\Chat('Test', false, $mockBackend);

        $result = iterator_to_array($chat());

        $this->assertSame([$chunk1, $chunk2], $result, 'Should yield raw JSON when asText=false');
    }
}
