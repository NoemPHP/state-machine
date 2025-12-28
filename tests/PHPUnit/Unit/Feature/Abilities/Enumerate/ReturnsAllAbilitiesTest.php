<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Enumerate;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\ConnectedRegions;
use Noem\State\Events;
use Noem\State\Chains\Notification;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: enumerate-abilities handler retrieves all abilities from registry
 *
 * Intent: Queries registry for complete ability set, enabling full enumeration
 */
#[Group('abilities')]
#[Group('enumerate-abilities-builtin')]
class ReturnsAllAbilitiesTest extends TestCase
{
    #[Test]
    public function handlerReturnsAllRegisteredAbilities(): void
    {
        $chainMail = new ChainMail();

        $chainMail->supply(
            fn(): DispatchAction => new DispatchAction(new ConnectedRegions(), new Events(new \Noem\State\Chains\ValidateCallback(), new \Noem\State\Chains\PrepareInvokable(), new \Noem\State\Chains\InvokeCallback(), new \Noem\State\Callbacks\CallbackRegistry())),
            fn(): Notification => new Notification(),
            fn(): AbilityRegistry => new AbilityRegistry()
        );

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $registry = $chainMail->get(AbilityRegistry::class);

        // Register a custom ability for testing
        $customAbility = new AbilityDefinition(
            name: 'test-ability',
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn() => ['result' => 'test']
        );
        $registry->register($customAbility);

        // Get the enumerate-abilities ability
        $enumerateAbility = $registry->get('enumerate-abilities');
        $handler = $enumerateAbility->handler;

        // Execute the handler (no filter)
        $result = $handler(null);

        $this->assertIsArray(
            $result,
            'Handler should return an array'
        );

        $this->assertArrayHasKey(
            'abilities',
            $result,
            'Result should have abilities key'
        );

        $abilities = $result['abilities'];
        $this->assertIsArray(
            $abilities,
            'abilities should be an array'
        );

        // Should include both enumerate-abilities and test-ability
        $this->assertGreaterThanOrEqual(
            2,
            count($abilities),
            'Should return at least enumerate-abilities and test-ability'
        );

        $abilityNames = array_column($abilities, 'name');
        $this->assertContains(
            'enumerate-abilities',
            $abilityNames,
            'Should include enumerate-abilities itself (meta-reflexive)'
        );

        $this->assertContains(
            'test-ability',
            $abilityNames,
            'Should include custom registered ability'
        );
    }
}
