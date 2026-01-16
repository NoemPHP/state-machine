<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion extracts text from JSON responses when asText is true
 */
#[Group('ai'), Group('completion-api')]
class ExtractsTextFromJsonTest extends TestCase
{
    #[Test]
    public function extractsTextFromJsonTest(): void
    {
        // Test that text is extracted from JSON when asText=true
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);
        $mockBackend->method('stream')
            ->willReturn((function() {
                yield ['choices' => [['text' => 'Extracted', 'other' => 'ignored']]];
                yield ['choices' => [['text' => ' text']]];
            })());

        $completion = new \Noem\State\Feature\Ai\Completion('Test', true, $mockBackend);

        $result = implode('', iterator_to_array($completion()));

        $this->assertSame('Extracted text', $result, 'Should extract text field from JSON responses');
    }
}
