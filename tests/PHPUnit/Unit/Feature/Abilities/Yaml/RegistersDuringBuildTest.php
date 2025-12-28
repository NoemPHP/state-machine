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
final class RegistersDuringBuildTest extends RegionBuilderTestCase
{
    public function testYamlAbilitiesRegisteredDuringRegionBuild(): void
    {
        $config = [
            'abilities' => [
                [
                    'name' => 'yaml-ability',
                    'handler' => fn(mixed $params) => ['result' => 'yaml'],
                ],
            ],
            'states' => [
                [
                    'name' => 'idle',
                ],
            ],
        ];

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new RegionLoader(),
            new ExtendedState(),
            new MessageFeature(),
            new AbilitiesFeature()
        );

        $region = $builder->build([
            'loader' => [
                'array' => $config,
            ],
        ]);

        // Access the registry directly from ChainMail to verify abilities were registered
        $registry = $builder->chainMail->get(AbilityRegistry::class);
        $ability = $registry->get('yaml-ability');

        $this->assertNotNull(
            $ability,
            'YAML abilities must be registered during build process'
        );
        $this->assertSame('yaml-ability', $ability->name);
    }
}
