<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion processes response buffer incrementally
 */
#[Group('ai'), Group('completion-api')]
class ProcessesBufferIncrementallyTest extends TestCase
{
    #[Test]
    public function processesBufferIncrementally(): void
    {
        // Test that chunks are yielded immediately without waiting for full response
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend->method('stream')
            ->willReturn((function() {
                yield ['choices' => [['text' => 'First']]];
                yield ['choices' => [['text' => ' chunk']]];
            })());

        $completion = new \Noem\State\Feature\Ai\Completion('Test', true, $mockBackend);

        $generator = $completion();

        // First chunk should be available immediately
        $this->assertSame('First', $generator->current());
        $generator->next();

        // Second chunk should be available after advancing
        $this->assertSame(' chunk', $generator->current());
    }
}
