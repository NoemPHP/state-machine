<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities;

use Noem\State\Chains\Notification;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\Chains\ProcessAbilityResult;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilitiesFeature registers ProcessAbilityResult chain in ChainMail
 *
 * Intent: Provides extension point for async result processing, enabling AsyncFeature
 * to hook response delivery without coupling
 */
#[Group('abilities')]
#[Group('feature-registration')]
class RegistersProcessAbilityResultChainTest extends TestCase
{
    public function testRegistersProcessAbilityResultChain(): void
    {
        $chainMail = new ChainMail();

        // Mock Notification dependency
        $notificationChain = $this->createMock(Notification::class);
        $chainMail->supply(fn(): Notification => $notificationChain);

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $processAbilityResult = $chainMail->get(ProcessAbilityResult::class);
        $this->assertInstanceOf(ProcessAbilityResult::class, $processAbilityResult);
    }
}
