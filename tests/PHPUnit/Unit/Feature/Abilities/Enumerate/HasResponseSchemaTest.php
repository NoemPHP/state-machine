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
 * Acceptance Criterion: enumerate-abilities has responseSchema defining abilities array structure
 *
 * Intent: Documents response structure for schema-aware consumers, ensuring type-safe responses
 */
#[Group('abilities')]
#[Group('enumerate-abilities-builtin')]
class HasResponseSchemaTest extends TestCase
{
    #[Test]
    public function enumerateAbilityHasResponseSchemaDefiningAbilitiesArray(): void
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
        $enumerateAbility = $registry->get('enumerate-abilities');

        $this->assertIsArray(
            $enumerateAbility->responseSchema,
            'Response schema must be an array'
        );

        // The schema should define an abilities property
        $this->assertArrayHasKey(
            'properties',
            $enumerateAbility->responseSchema,
            'Response schema should have properties'
        );

        $this->assertArrayHasKey(
            'abilities',
            $enumerateAbility->responseSchema['properties'],
            'Response schema should have abilities property'
        );

        // abilities should be an array
        $abilitiesSchema = $enumerateAbility->responseSchema['properties']['abilities'];
        $this->assertSame(
            'array',
            $abilitiesSchema['type'] ?? null,
            'abilities property should be of type array'
        );

        // Each item should be an object with name, description, schemas
        $this->assertArrayHasKey(
            'items',
            $abilitiesSchema,
            'abilities array should define item schema'
        );
    }
}
