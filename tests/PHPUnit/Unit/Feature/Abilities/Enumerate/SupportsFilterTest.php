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
 * Acceptance Criterion: enumerate-abilities handler filters by regex when filter parameter provided
 *
 * Intent: Applies regex pattern to ability names, returning only matching subset
 */
#[Group('abilities')]
#[Group('enumerate-abilities-builtin')]
class SupportsFilterTest extends TestCase
{
    #[Test]
    public function handlerFiltersAbilitiesByRegexWhenFilterProvided(): void
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

        // Register multiple custom abilities with different name patterns
        $registry->register(
            new AbilityDefinition(
                name: 'user-create',
                description: 'Create user',
                parameterSchema: [],
                responseSchema: [],
                handler: fn() => ['result' => 'created']
            )
        );

        $registry->register(
            new AbilityDefinition(
                name: 'user-delete',
                description: 'Delete user',
                parameterSchema: [],
                responseSchema: [],
                handler: fn() => ['result' => 'deleted']
            )
        );

        $registry->register(
            new AbilityDefinition(
                name: 'post-create',
                description: 'Create post',
                parameterSchema: [],
                responseSchema: [],
                handler: fn() => ['result' => 'created']
            )
        );

        // Get the enumerate-abilities ability
        $enumerateAbility = $registry->get('enumerate-abilities');
        $handler = $enumerateAbility->handler;

        // Execute handler with filter for 'user-*' abilities
        $result = $handler(['filter' => '/^user-/']);
        $abilities = $result['abilities'];

        $this->assertIsArray(
            $abilities,
            'Filtered result should be an array'
        );

        $abilityNames = array_column($abilities, 'name');

        // Should include user-* abilities
        $this->assertContains(
            'user-create',
            $abilityNames,
            'Filter should include user-create'
        );

        $this->assertContains(
            'user-delete',
            $abilityNames,
            'Filter should include user-delete'
        );

        // Should NOT include post-create or enumerate-abilities
        $this->assertNotContains(
            'post-create',
            $abilityNames,
            'Filter should exclude post-create (does not match pattern)'
        );

        $this->assertNotContains(
            'enumerate-abilities',
            $abilityNames,
            'Filter should exclude enumerate-abilities (does not match pattern)'
        );

        // Verify count
        $this->assertCount(
            2,
            $abilities,
            'Should return exactly 2 abilities matching the filter'
        );
    }
}
