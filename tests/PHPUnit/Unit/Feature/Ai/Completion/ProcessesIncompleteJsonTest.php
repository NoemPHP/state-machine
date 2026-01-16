<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion processes incomplete JSON lines correctly
 */
#[Group('ai'), Group('completion-api')]
class ProcessesIncompleteJsonTest extends TestCase
{
    #[Test]
    public function processesIncompleteJsonTest(): void
    {
        // Test that incomplete JSON chunks (missing text field) are handled gracefully
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend->method('stream')
            ->willReturn((function() {
                // Chunk with missing text field
                yield ['choices' => [['delta' => 'content']]];
                // Normal chunk
                yield ['choices' => [['text' => 'Valid']]];
                // Chunk with empty text
                yield ['choices' => [['text' => '']]];
            })());

        $completion = new \Noem\State\Feature\Ai\Completion('Test', true, $mockBackend);

        $result = implode('', iterator_to_array($completion()));

        // Should handle missing text field gracefully (defaults to empty string)
        $this->assertSame('Valid', $result, 'Should handle incomplete JSON chunks gracefully');
    }
}
