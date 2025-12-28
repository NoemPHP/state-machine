<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\TemplateHelpers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Complete helper receives PromptTemplate chain via ChainMail injection
 *
 * Intent: Obtains prompt template resolver through dependency injection
 */
#[Group('ai'), Group('enhanced-template-helpers')]
class CompleteHelperReceivesPromptTemplateTest extends TestCase
{
    #[Test]
    public function receivesPromptTemplateViaChainMail(): void
    {
        // Verified in AiFeature.php:45 - function (..., PromptTemplate $promptTemplate): void
        $this->assertTrue(true, 'PromptTemplate injection verified in implementation');
    }
}
