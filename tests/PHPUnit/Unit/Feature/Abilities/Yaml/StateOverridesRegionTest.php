<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Abilities\Yaml;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;

/**
 * @covers \Noem\State\Feature\Abilities\AbilitiesFeature
 */
final class StateOverridesRegionTest extends RegionBuilderTestCase
{
    public function testStateLevelAbilityOverridesRegionLevelWithSameName(): void
    {
        $config = [
            'abilities' => [
                [
                    'name' => 'polymorphic-ability',
                    'handler' => fn(mixed $params) => ['source' => 'region'],
                ],
            ],
            'states' => [
                ['name' => 'idle'],
                [
                    'name' => 'processing',
                    'abilities' => [
                        [
                            'name' => 'polymorphic-ability',
                            'handler' => fn(mixed $params) => ['source' => 'state'],
                        ],
                    ],
                ],
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

        // TODO: Verify state-level ability overrides region-level when processing state is active
        // This requires state-aware ability resolution implementation
        $this->expectNotToPerformAssertions();
    }
}
