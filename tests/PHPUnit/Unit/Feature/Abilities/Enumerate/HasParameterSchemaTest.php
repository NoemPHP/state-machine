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
 * Acceptance Criterion: enumerate-abilities has parameterSchema with optional filter property
 *
 * Intent: Enables filtered enumeration by ability name pattern, supporting targeted discovery
 */
#[Group('abilities')]
#[Group('enumerate-abilities-builtin')]
class HasParameterSchemaTest extends TestCase
{
    #[Test]
    public function enumerateAbilityHasParameterSchemaWithOptionalFilter(): void
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
            $enumerateAbility->parameterSchema,
            'Parameter schema must be an array'
        );

        // The schema should define filter as an optional property
        $this->assertArrayHasKey(
            'properties',
            $enumerateAbility->parameterSchema,
            'Parameter schema should have properties'
        );

        $this->assertArrayHasKey(
            'filter',
            $enumerateAbility->parameterSchema['properties'],
            'Parameter schema should have optional filter property'
        );

        // Filter should be a string (regex pattern)
        $filterSchema = $enumerateAbility->parameterSchema['properties']['filter'];
        $this->assertSame(
            'string',
            $filterSchema['type'] ?? null,
            'Filter property should be of type string'
        );
    }
}
