<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilitiesFeature registers AbilityRegistry Mesh in ChainMail
 *
 * Intent: Provides centralized ability storage through AbilityRegistry Mesh registration,
 * enabling per-region ability definitions and discovery
 */
#[Group('abilities')]
#[Group('feature-registration')]
class RegistersAbilityRegistryTest extends TestCase
{
    public function testRegistersAbilityRegistry(): void
    {
        $chainMail = new ChainMail();

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $registry = $chainMail->get(AbilityRegistry::class);
        $this->assertInstanceOf(AbilityRegistry::class, $registry);
    }
}
