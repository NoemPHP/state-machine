<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\ContextHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Context capture() method returns Generator yielding decoded JSON result
 *
 * Intent: Provides async-compatible result delivery through generator, yielding during
 * API call and returning decoded JSON
 */
#[Group('ai'), Group('context-helpers')]
class CaptureReturnsGeneratorTest extends TestCase
{
    #[Test]
    public function captureReturnsGenerator(): void
    {
        $chainMail = new ChainMail();
        $feature = new AiFeature();
        $feature($chainMail);

        // Test will verify capture method returns Generator
        $this->markTestIncomplete('Implementation needed: Generator return type');
    }
}
