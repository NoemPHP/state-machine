<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Resolver singleton prevents concurrent resolutions
 * Intent: Ensures only one resolution per property at a time, preventing duplicate work
 */
#[Group('async'), Group('integration'), Group('resolver')]
class ResolverSingletonTest extends TestCase
{
    public function testResolverSingletonPreventsConcurrentResolutions(): void
    {
        // RED TEST: Resolver feature not yet fully implemented
        $this->markTestIncomplete(
            'Resolver singleton test awaiting AddResolver AsyncConfig implementation. ' .
            'When implemented, this should test that multiple accesses to the same ' .
            'resolver property do not start concurrent resolution tasks.'
        );

        /*
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $resolutionStarts = 0;

        $resolver = function ($context) use (&$resolutionStarts) {
            $resolutionStarts++;
            for ($i = 0; $i < 50; $i++) {
                yield;
            }
            return 'result';
        };

        $config = new AsyncConfig(singleton: true);
        $builder->addResolver('prop', $resolver, $config);

        $region = $builder->build();
        $context = $region->getContext();

        // First access starts resolution
        $context['prop'];
        $this->assertEquals(1, $resolutionStarts);

        // Multiple accesses while resolving - singleton prevents new starts
        for ($i = 0; $i < 10; $i++) {
            $context['prop'];
        }

        $this->assertEquals(1, $resolutionStarts, 'Singleton should prevent concurrent resolutions');
        */
    }
}
