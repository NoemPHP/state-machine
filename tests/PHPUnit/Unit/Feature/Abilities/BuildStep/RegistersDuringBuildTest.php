<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\BuildStep;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\BuildStep\RegisterAbility;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;

/**
 * @covers \Noem\State\Feature\Abilities\BuildStep\RegisterAbility
 */
final class RegistersDuringBuildTest extends RegionBuilderTestCase
{
    public function testAbilitiesRegisteredBeforeFirstStateEntry(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];
        $abilityAvailable = false;

        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AbilitiesFeature()
            )
            ->addBuildStep(new RegisterAbility(
                name: 'test-ability',
                handler: $handler
            ))
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$abilityAvailable) {
                // Check if ability is available on first state entry
                $registry = $this->get('__chainMail')->get(AbilityRegistry::class);
                $abilityAvailable = $registry->get('test-ability') !== null;
            })
            ->build();

        $this->assertTrue(
            $abilityAvailable,
            'Abilities must be registered during build process, before first state entry'
        );
    }
}
