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
final class AcceptsParameterSchemaTest extends RegionBuilderTestCase
{
    public function testYamlAbilityAcceptsOptionalParameterSchema(): void
    {
        $config = [
            'abilities' => [
                [
                    'name' => 'validated-ability',
                    'handler' => fn(mixed $params) => ['result' => 'test'],
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                        ],
                        'required' => ['name'],
                    ],
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
        // If parameterSchema is not accepted, this will throw schema validation error
    }
}
