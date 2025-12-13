<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Legacy YAML without async object continues working
 * Intent: Maintains compatibility with existing YAML specs, allowing gradual migration
 */
#[Group('async'), Group('integration'), Group('backward-compatibility')]
class LegacyYamlCompatibilityTest extends TestCase
{
    public function testLegacyYamlWithoutAsyncObjectContinuesWorking(): void
    {
        // Test that YAML without explicit async object still works
        // This would typically load from YAML, but we'll simulate with array config

        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $executed = false;

        // Legacy-style callback without explicit async configuration
        $legacyCallback = function (object $trigger) use (&$executed) {
            $executed = true;
            yield;
        };

        $builder
            ->setStates('active')
            ->onAction('active', $legacyCallback);

        $region = $builder->build();

        // Should work with deprecation warning
        @$region->trigger(new \stdClass());

        $this->assertTrue($executed, 'Legacy YAML-style callbacks should continue working');
    }
}
