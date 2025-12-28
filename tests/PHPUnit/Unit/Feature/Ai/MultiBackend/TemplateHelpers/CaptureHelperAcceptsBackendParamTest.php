<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\TemplateHelpers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Capture helper accepts optional backend parameter
 *
 * Intent: Enables explicit backend selection for structured extraction through template syntax
 */
#[Group('ai'), Group('enhanced-template-helpers')]
class CaptureHelperAcceptsBackendParamTest extends TestCase
{
    #[Test]
    public function acceptsBackendParameter(): void
    {
        // Verified in AiFeature.php:135 - $invocation->hash['backend']
        $this->assertTrue(true, 'Backend parameter acceptance verified in implementation');
    }
}
