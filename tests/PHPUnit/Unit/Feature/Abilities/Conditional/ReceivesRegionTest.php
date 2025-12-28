<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\Conditional;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;

/**
 * @covers \Noem\State\Feature\Abilities\AbilityDefinition
 */
final class ReceivesRegionTest extends RegionBuilderTestCase
{
    public function testPredicateReceivesRegionAsParameter(): void
    {
        $receivedRegion = null;

        $predicate = function (Region $region) use (&$receivedRegion) {
            $receivedRegion = $region;
            return true;
        };

        $handler = fn(array $params) => ['result' => 'test'];

        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$receivedRegion, $predicate, $handler) {
                $registry = $this->get('__chainMail')->get(AbilityRegistry::class);

                $registry->register(new AbilityDefinition(
                    name: 'test-ability',
                    description: 'Test',
                    parameterSchema: [],
                    responseSchema: [],
                    handler: $handler,
                    predicate: $predicate
                ));

                // Invoke to trigger predicate
                $this->abilities('test-ability');
            })
            ->build();

        $this->assertSame(
            $region,
            $receivedRegion,
            'Predicate must receive region as parameter for context evaluation'
        );
    }
}
