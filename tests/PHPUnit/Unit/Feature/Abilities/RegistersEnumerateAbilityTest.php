<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\ConnectedRegions;
use Noem\State\Events;
use Noem\State\Chains\Notification;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilitiesFeature registers built-in enumerate-abilities ability
 *
 * Intent: Provides meta-reflexive discovery by registering enumerate-abilities as native ability,
 * enabling introspection through same API
 */
#[Group('abilities')]
#[Group('feature-registration')]
class RegistersEnumerateAbilityTest extends TestCase
{
    public function testRegistersEnumerateAbilitiesAbility(): void
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
            'AbilitiesFeature should register built-in enumerate-abilities ability'
        );

        // Verify it has the expected properties
        $this->assertSame(
            'enumerate-abilities',
            $enumerateAbility->name,
            'Ability should be named enumerate-abilities'
        );

        $this->assertNotEmpty(
            $enumerateAbility->description,
            'Ability should have a description'
        );

        $this->assertIsArray(
            $enumerateAbility->parameterSchema,
            'Ability should have parameter schema'
        );

        $this->assertIsArray(
            $enumerateAbility->responseSchema,
            'Ability should have response schema'
        );

        $this->assertIsCallable(
            $enumerateAbility->handler,
            'Ability should have a callable handler'
        );
    }
}
