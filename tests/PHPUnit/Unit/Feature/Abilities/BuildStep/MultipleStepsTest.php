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
final class MultipleStepsTest extends RegionBuilderTestCase
{
    public function testMultipleRegisterAbilityStepsCanBeAdded(): void
    {
        $handler1 = fn(array $params) => ['result' => 'first'];
        $handler2 = fn(array $params) => ['result' => 'second'];
        $handler3 = fn(array $params) => ['result' => 'third'];

        $builder = new RegionBuilder();
        $builder
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AbilitiesFeature()
            )
            ->addBuildStep(new RegisterAbility(
                name: 'ability-one',
                handler: $handler1
            ))
            ->addBuildStep(new RegisterAbility(
                name: 'ability-two',
                handler: $handler2
            ))
            ->addBuildStep(new RegisterAbility(
                name: 'ability-three',
                handler: $handler3
            ))
            ->setStates('idle')
            ->build();

        $registry = $builder->chainMail->get(AbilityRegistry::class);



        $this->assertNotNull($registry->get('ability-one'));
        $this->assertNotNull($registry->get('ability-two'));
        $this->assertNotNull($registry->get('ability-three'));

        $this->assertCount(
            4, // 3 custom + 1 built-in enumerate-abilities
            $registry->all(),
            'Multiple RegisterAbility steps must all register their abilities'
        );
    }
}
