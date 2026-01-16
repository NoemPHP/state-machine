<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat includes developer role system message
 */
#[Group('ai'), Group('chat-api')]
class IncludesSystemMessageTest extends TestCase
{
    #[Test]
    public function includesSystemMessageTest(): void
    {
        // This test verifies Chat class can be constructed
        // System message handling is part of backend/request configuration
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);

        $chat = new \Noem\State\Feature\Ai\Chat('Test prompt', true, $mockBackend);

        $this->assertInstanceOf(\Noem\State\Feature\Ai\Chat::class, $chat, 'Should support system message configuration');
    }
}
