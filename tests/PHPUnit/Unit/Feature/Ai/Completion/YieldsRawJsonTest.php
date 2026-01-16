<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion yields raw JSON responses when asText is false
 */
#[Group('ai'), Group('completion-api')]
class YieldsRawJsonTest extends TestCase
{
    #[Test]
    public function yieldsRawJsonTest(): void
    {
        // Test that raw JSON is yielded when asText=false
        $chunk1 = ['choices' => [['text' => 'Hello', 'finish_reason' => null]]];
        $chunk2 = ['choices' => [['text' => ' world', 'finish_reason' => 'stop']]];

        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend->method('stream')
            ->willReturn((function() use ($chunk1, $chunk2) {
                yield $chunk1;
                yield $chunk2;
            })());

        $completion = new \Noem\State\Feature\Ai\Completion('Test', false, $mockBackend);

        $result = iterator_to_array($completion());

        $this->assertSame([$chunk1, $chunk2], $result, 'Should yield raw JSON chunks when asText is false');
    }
}
