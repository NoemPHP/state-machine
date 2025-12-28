<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\Conditional;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityNotFoundException;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;

/**
 * @covers \Noem\State\Feature\Abilities\InvokeAbility
 */
final class FalseHidesTest extends RegionBuilderTestCase
{
    public function testPredicateReturningFalseHidesAbility(): void
    {
        $predicate = fn(Region $region) => false;
        $handler = fn(array $params) => ['result' => 'should-not-run'];

        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use ($predicate, $handler) {
                $registry = $this->get('__chainMail')->get(AbilityRegistry::class);

                $registry->register(new AbilityDefinition(
                    name: 'hidden-ability',
                    description: 'Test',
                    parameterSchema: [],
                    responseSchema: [],
                    handler: $handler,
                    predicate: $predicate
                ));

                $this->expectException(AbilityNotFoundException::class);
                $this->abilities('hidden-ability');
            })
            ->build();

        $this->expectNotToPerformAssertions();
    }
}
