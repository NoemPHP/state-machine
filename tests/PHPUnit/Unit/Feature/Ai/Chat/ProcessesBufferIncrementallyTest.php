<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Chat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chat processes response buffer incrementally
 */
#[Group('ai'), Group('chat-api')]
class ProcessesBufferIncrementallyTest extends TestCase
{
    #[Test]
    public function processesBufferIncrementallyTest(): void
    {
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend->method('stream')
            ->willReturn((function() {
                yield ['choices' => [['message' => ['content' => 'First']]]];
                yield ['choices' => [['message' => ['content' => ' chunk']]]];
            })());

        $chat = new \Noem\State\Feature\Ai\Chat('Test', true, $mockBackend);

        $generator = $chat();

        // First chunk available immediately
        $this->assertSame('First', $generator->current());
        $generator->next();

        // Second chunk available after advancing
        $this->assertSame(' chunk', $generator->current());
    }
}
