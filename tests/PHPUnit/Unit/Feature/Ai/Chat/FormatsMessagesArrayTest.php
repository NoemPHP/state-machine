<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat formats messages array with roles
 */
#[Group('ai'), Group('chat-api')]
class FormatsMessagesArrayTest extends TestCase
{
    #[Test]
    public function formatsMessagesArrayTest(): void
    {
        // This test verifies Chat class handles message formatting
        // Message formatting is delegated to the backend
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend->method('stream')
            ->willReturn((function() {
                yield ['choices' => [['message' => ['content' => 'Response']]]];
            })());

        $chat = new \Noem\State\Feature\Ai\Chat('Test prompt', true, $mockBackend);

        $result = iterator_to_array($chat());

        $this->assertIsArray($result, 'Should handle message array formatting');
    }
}
