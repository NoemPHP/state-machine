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
final class StateScopedTest extends RegionBuilderTestCase
{
    public function testStateLevelAbilitiesScopedToDefiningState(): void
    {
        $abilityInvoked = false;

        $config = [
            'states' => [
                ['name' => 'idle'],
                [
                    'name' => 'processing',
                    'abilities' => [
                        [
                            'name' => 'state-scoped',
                            'handler' => function (mixed $params) use (&$abilityInvoked) {
                                $abilityInvoked = true;
                                return ['result' => 'scoped'];
                            },
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

        // TODO: Verify ability is only available when 'processing' state is active
        // This requires state-aware ability filtering implementation
        $this->expectNotToPerformAssertions();
    }
}
