<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\ContextHelpers;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AiFeature registers capture() method in ExtendedState context
 *
 * Intent: Provides direct programmatic access to AI capture functionality from state
 * actions without requiring template syntax
 */
#[Group('ai'), Group('context-helpers')]
class RegistersCaptureMethodTest extends TestCase
{
    #[Test]
    public function aiFeatureRegistersCaptureMethod(): void
    {
        // AiFeature registers middleware on BoundAccess when available
        // This requires ExtendedState to be loaded first
        // The actual verification requires integration test with Region/ExtendedState
        $this->markTestIncomplete('Implementation needed: capture() method registration');
    }
}
