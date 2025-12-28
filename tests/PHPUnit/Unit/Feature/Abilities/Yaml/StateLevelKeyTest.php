<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Abilities\Yaml;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\ProcessArray;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;

/**
 * @covers \Noem\State\Feature\Abilities\AbilitiesFeature
 */
final class StateLevelKeyTest extends RegionBuilderTestCase
{
    public function testAbilitiesKeySupportedAtStateLevel(): void
    {
        $config = [
            'states' => [
                [
                    'name' => 'processing',
                    'abilities' => [
                        [
                            'name' => 'state-specific-ability',
                            'handler' => fn(mixed $params) => ['result' => 'state'],
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

        $this->expectNotToPerformAssertions();
        // If abilities key is not supported at state level, this will throw schema validation error
    }
}
