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
final class DefaultsNoPredicateTest extends RegionBuilderTestCase
{
    public function testCreatesAlwaysAvailableAbilityWithoutPredicate(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];

        $builder = new RegionBuilder();
        $builder
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AbilitiesFeature()
            )
            ->addBuildStep(new RegisterAbility(
                name: 'always-available',
                handler: $handler
            ))
            ->setStates('idle')
            ->build();

        $registry = $builder->chainMail->get(AbilityRegistry::class);


        $definition = $registry->get('always-available');

        $this->assertNull(
            $definition->predicate,
            'Ability without predicate must have null predicate (always available)'
        );
    }
}
