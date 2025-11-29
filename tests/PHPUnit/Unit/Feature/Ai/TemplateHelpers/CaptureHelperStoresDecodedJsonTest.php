<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\TemplateHelpers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Capture helper stores decoded JSON in template data
 */
#[Group('ai'), Group('template-helpers')]
class CaptureHelperStoresDecodedJsonTest extends TestCase
{
    #[Test]
    public function captureHelperStoresDecodedJsonTest(): void
    {
        $this->markTestIncomplete('Spec approved, implementation pending');
    }
}
