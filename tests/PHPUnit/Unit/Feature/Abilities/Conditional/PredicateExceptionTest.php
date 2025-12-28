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
final class PredicateExceptionTest extends RegionBuilderTestCase
{
    public function testPredicateExceptionsPropagateAsAbilityNotFoundException(): void
    {
        $this->expectException(AbilityNotFoundException::class);

        $predicate = function (Region $region) {
            throw new \RuntimeException('Predicate evaluation failed');
        };

        $handler = fn(array $params) => ['result' => 'test'];

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
                    name: 'failing-predicate',
                    description: 'Test',
                    parameterSchema: [],
                    responseSchema: [],
                    handler: $handler,
                    predicate: $predicate
                ));

                $this->abilities('failing-predicate');
            })
            ->build();
    }
}
