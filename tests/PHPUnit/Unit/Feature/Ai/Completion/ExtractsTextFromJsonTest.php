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
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
