<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat extracts text from message content when asText is true
 */
#[Group('ai'), Group('chat-api')]
class ExtractsMessageContentTest extends TestCase
{
    #[Test]
    public function extractsMessageContentTest(): void
    {
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend->method('stream')
            ->willReturn((function() {
                yield ['choices' => [['message' => ['content' => 'Extracted', 'role' => 'assistant']]]];
                yield ['choices' => [['message' => ['content' => ' content']]]];
            })());

        $chat = new \Noem\State\Feature\Ai\Chat('Test', true, $mockBackend);

        $result = implode('', iterator_to_array($chat()));

        $this->assertSame('Extracted content', $result, 'Should extract content field from message when asText=true');
    }
}
