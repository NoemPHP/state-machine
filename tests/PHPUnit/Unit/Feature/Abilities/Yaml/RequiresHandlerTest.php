<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Abilities\Yaml;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;

/**
 * @covers \Noem\State\Feature\Abilities\AbilitiesFeature
 */
final class RequiresHandlerTest extends RegionBuilderTestCase
{
    public function testYamlAbilityRequiresHandlerProperty(): void
    {
        $config = [
            'abilities' => [
                [
                    'name' => 'test-ability',
                    // Missing 'handler' property
                ],
            ],
            'states' => [
                ['name' => 'idle'],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/handler/i');

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
    }
}
