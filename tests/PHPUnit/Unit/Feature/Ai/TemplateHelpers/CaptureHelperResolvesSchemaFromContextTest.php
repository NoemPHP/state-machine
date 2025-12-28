<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\TemplateHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Capture helper resolves schema from template data context when
 * schema parameter is string reference
 *
 * Intent: Enables schema reuse by supporting context variable references in schema parameter,
 * retrieving schema definition from template data at runtime
 */
#[Group('ai'), Group('template-helpers')]
class CaptureHelperResolvesSchemaFromContextTest extends TestCase
{
    #[Test]
    public function captureHelperResolvesSchemaFromContext(): void
    {
        $chainMail = new ChainMail();
        $feature = new AiFeature();
        $feature($chainMail);

        // Test will verify that when schema parameter is a string like "mySchema",
        // the helper looks it up in invocation data context

        // This requires actual invocation with data containing schema definition
        $this->markTestIncomplete('Implementation needed: schema resolution from context');
    }
}
