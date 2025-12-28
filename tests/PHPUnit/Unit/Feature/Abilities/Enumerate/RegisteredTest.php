<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Enumerate;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\ConnectedRegions;
use Noem\State\Events;
use Noem\State\Chains\Notification;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: enumerate-abilities AbilityDefinition is registered during feature initialization
 *
 * Intent: Provides built-in enumeration ability available immediately after feature load
 */
#[Group('abilities')]
#[Group('enumerate-abilities-builtin')]
class RegisteredTest extends TestCase
{
    #[Test]
    public function enumerateAbilityIsRegisteredDuringFeatureInitialization(): void
    {
        $chainMail = new ChainMail();

        // Provide dependencies
        $chainMail->supply(
            fn(): DispatchAction => new DispatchAction(new ConnectedRegions(), new Events(new \Noem\State\Chains\ValidateCallback(), new \Noem\State\Chains\PrepareInvokable(), new \Noem\State\Chains\InvokeCallback(), new \Noem\State\Callbacks\CallbackRegistry())),
            fn(): Notification => new Notification(),
            fn(): AbilityRegistry => new AbilityRegistry()
        );

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        // Get the registry
        $registry = $chainMail->get(AbilityRegistry::class);
        $this->assertInstanceOf(AbilityRegistry::class, $registry);

        // Verify that 'enumerate-abilities' is registered
        $enumerateAbility = $registry->get('enumerate-abilities');

        $this->assertNotNull(
            $enumerateAbility,
            'enumerate-abilities should be registered during feature initialization'
        );
    }
}
