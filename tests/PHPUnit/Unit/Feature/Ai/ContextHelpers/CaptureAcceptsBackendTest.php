<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\ContextHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Context capture() method accepts optional backend parameter
 *
 * Intent: Enables backend selection through backend parameter, defaulting to
 * 'openai' when not specified
 */
#[Group('ai'), Group('context-helpers')]
class CaptureAcceptsBackendTest extends TestCase
{
    #[Test]
    public function captureAcceptsOptionalBackend(): void
    {
        $chainMail = new ChainMail();
        $feature = new AiFeature();
        $feature($chainMail);

        // Test will verify capture method signature accepts optional backend string
        $this->markTestIncomplete('Implementation needed: backend parameter');
    }
}
