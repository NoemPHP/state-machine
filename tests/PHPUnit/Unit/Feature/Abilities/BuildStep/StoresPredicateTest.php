<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\BuildStep;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\BuildStep\RegisterAbility;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;

/**
 * @covers \Noem\State\Feature\Abilities\BuildStep\RegisterAbility
 */
final class StoresPredicateTest extends RegionBuilderTestCase
{
    public function testStoresPredicateInDefinition(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];
        $predicate = fn(Region $region) => true;

        $builder = new RegionBuilder();
        $builder
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AbilitiesFeature()
            )
            ->addBuildStep(new RegisterAbility(
                name: 'test-ability',
                handler: $handler,
                predicate: $predicate
            ))
            ->setStates('idle')
            ->build();

        $registry = $builder->chainMail->get(AbilityRegistry::class);


        $definition = $registry->get('test-ability');

        $this->assertSame(
            $predicate,
            $definition->predicate,
            'RegisterAbility must store predicate in AbilityDefinition'
        );
    }
}
