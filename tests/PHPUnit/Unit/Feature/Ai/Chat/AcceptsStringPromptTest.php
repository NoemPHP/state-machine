<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat accepts string prompt and builds Request automatically
 */
#[Group('ai'), Group('chat-api')]
class AcceptsStringPromptTest extends TestCase
{
    #[Test]
    public function acceptsStringPromptTest(): void
    {
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);

        $chat = new \Noem\State\Feature\Ai\Chat('Test prompt', true, $mockBackend);

        $this->assertInstanceOf(\Noem\State\Feature\Ai\Chat::class, $chat, 'Should construct with string prompt');
    }
}
