<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\ContextHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Context capture() method accepts prompt string as first parameter
 *
 * Intent: Enables capture operations through simple function call with prompt text
 */
#[Group('ai'), Group('context-helpers')]
class CaptureAcceptsPromptTest extends TestCase
{
    #[Test]
    public function captureAcceptsPromptString(): void
    {
        $chainMail = new ChainMail();
        $feature = new AiFeature();
        $feature($chainMail);

        // Test will verify capture method signature accepts string prompt
        $this->markTestIncomplete('Implementation needed: prompt parameter');
    }
}
