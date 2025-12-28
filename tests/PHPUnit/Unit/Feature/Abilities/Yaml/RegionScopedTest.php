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
final class RegionScopedTest extends RegionBuilderTestCase
{
    public function testRegionLevelAbilitiesAvailableGlobally(): void
    {
        $config = [
            'abilities' => [
                [
                    'name' => 'global-ability',
                    'handler' => fn(mixed $params) => ['result' => 'global'],
                ],
            ],
            'states' => [
                ['name' => 'idle'],
                ['name' => 'processing'],
                ['name' => 'done'],
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
        // If region-level abilities are not supported, this will throw schema validation error
    }
}
