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
 * Acceptance Criterion: enumerate-abilities has human-readable description
 *
 * Intent: Provides documentation for ability purpose, supporting enumeration and introspection
 */
#[Group('abilities')]
#[Group('enumerate-abilities-builtin')]
class HasDescriptionTest extends TestCase
{
    #[Test]
    public function enumerateAbilityHasHumanReadableDescription(): void
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

        $this->assertNotEmpty(
            $enumerateAbility->description,
            'enumerate-abilities must have a human-readable description'
        );

        $this->assertIsString(
            $enumerateAbility->description,
            'Description must be a string'
        );

        $this->assertGreaterThan(
            10,
            strlen($enumerateAbility->description),
            'Description should be meaningful (more than 10 characters)'
        );
    }
}
