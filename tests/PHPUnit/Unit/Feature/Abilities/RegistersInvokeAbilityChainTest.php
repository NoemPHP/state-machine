<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities;

use Noem\State\Chains\Notification;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\Chains\ExecuteAbilityHandler;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\ProcessAbilityResult;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilitiesFeature registers InvokeAbility chain in ChainMail
 *
 * Intent: Provides ability invocation mechanism through InvokeAbility chain registration,
 * enabling middleware-interceptable ability calls
 */
#[Group('abilities')]
#[Group('feature-registration')]
class RegistersInvokeAbilityChainTest extends TestCase
{
    public function testRegistersInvokeAbilityChain(): void
    {
        $chainMail = new ChainMail();

        // Mock all dependencies that InvokeAbility needs
        $notificationChain = $this->createMock(Notification::class);
        $executeAbilityHandler = $this->createMock(ExecuteAbilityHandler::class);
        $processAbilityResult = $this->createMock(ProcessAbilityResult::class);

        $chainMail->supply(fn(): Notification => $notificationChain);
        $chainMail->supply(fn(): ExecuteAbilityHandler => $executeAbilityHandler);
        $chainMail->supply(fn(): ProcessAbilityResult => $processAbilityResult);

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $invokeAbility = $chainMail->get(InvokeAbility::class);
        $this->assertInstanceOf(InvokeAbility::class, $invokeAbility);
    }
}
