<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\Chains\ExecuteAbilityHandler;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilitiesFeature registers ExecuteAbilityHandler chain in ChainMail
 *
 * Intent: Provides hookable handler execution, enabling AsyncFeature to wrap generators as Tasks
 */
#[Group('abilities')]
#[Group('feature-registration')]
class RegistersExecuteAbilityHandlerChainTest extends TestCase
{
    public function testRegistersExecuteAbilityHandlerChain(): void
    {
        $chainMail = new ChainMail();

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $executeAbilityHandler = $chainMail->get(ExecuteAbilityHandler::class);
        $this->assertInstanceOf(ExecuteAbilityHandler::class, $executeAbilityHandler);
    }
}
