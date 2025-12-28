<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\TemplateHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Capture helper accepts inline JSON schema objects in schema parameter
 *
 * Intent: Enables ad-hoc schema definition through inline schema objects in template syntax
 */
#[Group('ai'), Group('template-helpers')]
class CaptureHelperAcceptsInlineSchemaTest extends TestCase
{
    #[Test]
    public function captureHelperAcceptsInlineSchema(): void
    {
        $chainMail = new ChainMail();
        $feature = new AiFeature();
        $feature($chainMail);

        // Test will verify that schema parameter can be an array (inline schema object)
        // rather than a string reference

        $this->markTestIncomplete('Implementation needed: inline schema object support');
    }
}
