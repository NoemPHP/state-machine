<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
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

        // Provide AbilityRegistry dependency that InvokeAbility requires
        $chainMail->supply(fn(): AbilityRegistry => new AbilityRegistry());

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $invokeAbility = $chainMail->get(InvokeAbility::class);
        $this->assertInstanceOf(InvokeAbility::class, $invokeAbility);
    }
}
