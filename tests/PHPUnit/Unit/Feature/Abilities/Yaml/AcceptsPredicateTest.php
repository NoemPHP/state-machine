<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Abilities\Yaml;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;

/**
 * @covers \Noem\State\Feature\Abilities\AbilitiesFeature
 */
final class AcceptsPredicateTest extends RegionBuilderTestCase
{
    public function testYamlAbilityAcceptsOptionalPredicate(): void
    {
        $config = [
            'abilities' => [
                [
                    'name' => 'conditional-ability',
                    'handler' => fn(mixed $params) => ['result' => 'test'],
                    'predicate' => fn(Region $region) => true,
                ],
            ],
            'states' => [
                ['name' => 'idle'],
            ],
        ];

        $region = (new RegionBuilder())
            ->enableFeatures(
                new RegionLoader(),
                new ExtendedState(),
                new MessageFeature(),
                new AbilitiesFeature()
            )
            ->build([
                'loader' => [
                    'array' => $config,
                ],
            ]);

        $this->expectNotToPerformAssertions();
        // If predicate is not accepted, this will throw schema validation error
    }
}
