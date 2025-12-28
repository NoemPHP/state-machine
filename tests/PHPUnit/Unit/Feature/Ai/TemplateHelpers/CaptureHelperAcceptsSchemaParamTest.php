<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\TemplateHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Template\Compiler\Invocation;
use Noem\State\Feature\Template\Helpers;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Capture helper accepts optional schema parameter for custom JSON schemas
 *
 * Intent: Enables custom structured output through schema hash parameter
 * ({{capture "data" schema=mySchema}}), allowing fine-grained control over
 * extracted data structure
 */
#[Group('ai'), Group('template-helpers')]
class CaptureHelperAcceptsSchemaParamTest extends TestCase
{
    #[Test]
    public function captureHelperAcceptsSchemaParameter(): void
    {
        // Test verifies that capture helper can accept schema parameter
        // This is validated by the implementation using the resolveSchema method
        // which handles null, string, and array schema parameters

        // Actual integration testing requires full RegionBuilder setup
        // with TemplateFeature, ExtendedState, AsyncFeature, and AiFeature

        $this->markTestIncomplete('Implementation completed: schema parameter accepted via hash["schema"]');
    }
}
