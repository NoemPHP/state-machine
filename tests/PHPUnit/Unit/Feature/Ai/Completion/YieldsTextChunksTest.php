<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion yields text chunks from streaming API
 */
#[Group('ai'), Group('completion-api')]
class YieldsTextChunksTest extends TestCase
{
    #[Test]
    public function yieldsTextChunks(): void
    {
        // Create mock backend that returns streaming JSON chunks
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend->method('stream')
            ->willReturn((function() {
                yield ['choices' => [['text' => 'Hello ']]];
                yield ['choices' => [['text' => 'world']]];
                yield ['choices' => [['text' => '!']]];
            })());

        $completion = new \Noem\State\Feature\Ai\Completion('Test prompt', true, $mockBackend);

        $result = iterator_to_array($completion());

        $this->assertSame(['Hello ', 'world', '!'], $result, 'Should yield text chunks incrementally');
    }
}
