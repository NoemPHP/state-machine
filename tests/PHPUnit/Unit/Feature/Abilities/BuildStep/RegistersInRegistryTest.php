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
final class RegistersInRegistryTest extends RegionBuilderTestCase
{
    public function testRegistersAbilityInRegistryDuringBuild(): void
    {
        $handler = fn(array $params) => ['sum' => array_sum($params['numbers'])];

        $builder = new RegionBuilder();
        $builder
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AbilitiesFeature()
            )
            ->addBuildStep(new RegisterAbility(
                name: 'calculate-sum',
                handler: $handler,
                description: 'Sum an array of numbers'
            ))
            ->setStates('idle')
            ->build();

        $registry = $builder->chainMail->get(AbilityRegistry::class);
        $definition = $registry->get('calculate-sum');

        $this->assertNotNull(
            $definition,
            'RegisterAbility must register ability in AbilityRegistry during build'
        );

        $this->assertEquals(
            'calculate-sum',
            $definition->name
        );
    }
}
