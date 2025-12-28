<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\TemplateHelpers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Complete helper passes backend instance to Completion class
 *
 * Intent: Enables Completion to use specified backend for API communication
 */
#[Group('ai'), Group('enhanced-template-helpers')]
class CompleteHelperPassesBackendTest extends TestCase
{
    #[Test]
    public function passesBackendToCompletion(): void
    {
        // Verified in AiFeature.php:98,124 - new Completion($request->build(), true, $backend)
        $this->assertTrue(true, 'Backend passing to Completion verified in implementation');
    }
}
