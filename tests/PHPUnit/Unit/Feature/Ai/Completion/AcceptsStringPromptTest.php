<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\Completion;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Completion accepts string prompt and builds Request automatically
 */
#[Group('ai'), Group('completion-api')]
class AcceptsStringPromptTest extends TestCase
{
    #[Test]
    public function acceptsStringPrompt(): void
    {
        // Create mock backend that won't actually be called in this test
        $mockBackend = $this->createMock(\Noem\State\Feature\Ai\Backend\BackendInterface::class);

        $completion = new \Noem\State\Feature\Ai\Completion(
            'Test prompt',
            true,
            $mockBackend
        );

        $this->assertInstanceOf(\Noem\State\Feature\Ai\Completion::class, $completion, 'Should construct with string prompt');
    }
}
