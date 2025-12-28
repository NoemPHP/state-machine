<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Multiple regions with separate ability registries
 *
 * Intent: Validates that each region maintains its own isolated ability registry
 *
 * @see specs/features/abilities.yaml - integration (derived from spec analysis)
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('multiple-regions')]
class MultipleRegionsTest extends TestCase
{
    #[Test]
    public function eachRegionHasIndependentRegistry(): void
    {
        // Given: Three independent regions
        $region1Abilities = null;
        $region2Abilities = null;
        $region3Abilities = null;

        // Region 1
        $region1 = (new RegionBuilder())
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$region1Abilities) {
                $this->abilities()->register('region1-ability', [
                    'name' => 'region1-ability',
                    'description' => 'Only in region 1',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['region' => 1]
                ]);

                $this->abilities('enumerate-abilities')
                    ->then(function ($response) use (&$region1Abilities) {
                        $region1Abilities = array_column($response->parameters['abilities'], 'name');
                    });
            })
            ->build();

        // Region 2
        $region2 = (new RegionBuilder())
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$region2Abilities) {
                $this->abilities()->register('region2-ability', [
                    'name' => 'region2-ability',
                    'description' => 'Only in region 2',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['region' => 2]
                ]);

                $this->abilities('enumerate-abilities')
                    ->then(function ($response) use (&$region2Abilities) {
                        $region2Abilities = array_column($response->parameters['abilities'], 'name');
                    });
            })
            ->build();

        // Region 3
        $region3 = (new RegionBuilder())
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$region3Abilities) {
                $this->abilities()->register('region3-ability', [
                    'name' => 'region3-ability',
                    'description' => 'Only in region 3',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['region' => 3]
                ]);

                $this->abilities('enumerate-abilities')
                    ->then(function ($response) use (&$region3Abilities) {
                        $region3Abilities = array_column($response->parameters['abilities'], 'name');
                    });
            })
            ->build();

        // When: Trigger all regions
        $region1->trigger((object)[]);
        $region2->trigger((object)[]);
        $region3->trigger((object)[]);

        // Then: Each region should have only its own abilities
        $this->assertContains('region1-ability', $region1Abilities);
        $this->assertNotContains('region2-ability', $region1Abilities);
        $this->assertNotContains('region3-ability', $region1Abilities);

        $this->assertContains('region2-ability', $region2Abilities);
        $this->assertNotContains('region1-ability', $region2Abilities);
        $this->assertNotContains('region3-ability', $region2Abilities);

        $this->assertContains('region3-ability', $region3Abilities);
        $this->assertNotContains('region1-ability', $region3Abilities);
        $this->assertNotContains('region2-ability', $region3Abilities);
    }

    #[Test]
    public function registryIsolationWithSameName(): void
    {
        // Given: Two regions with same ability name but different implementations
        $region1Response = null;
        $region2Response = null;

        $region1 = (new RegionBuilder())
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$region1Response) {
                $this->abilities()->register('shared-name', [
                    'name' => 'shared-name',
                    'description' => 'Region 1 implementation',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['source' => 'region1', 'value' => 100]
                ]);

                $this->abilities('shared-name')
                    ->then(function ($response) use (&$region1Response) {
                        $region1Response = $response;
                    });
            })
            ->build();

        $region2 = (new RegionBuilder())
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$region2Response) {
                $this->abilities()->register('shared-name', [
                    'name' => 'shared-name',
                    'description' => 'Region 2 implementation',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['source' => 'region2', 'value' => 200]
                ]);

                $this->abilities('shared-name')
                    ->then(function ($response) use (&$region2Response) {
                        $region2Response = $response;
                    });
            })
            ->build();

        // When: Trigger both
        $region1->trigger((object)[]);
        $region2->trigger((object)[]);

        // Then: Each region should invoke its own implementation
        $this->assertSame('region1', $region1Response->parameters['source']);
        $this->assertSame(100, $region1Response->parameters['value']);

        $this->assertSame('region2', $region2Response->parameters['source']);
        $this->assertSame(200, $region2Response->parameters['value']);
    }
}
