<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\Feature;
use Noem\State\Feature\FeatureRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: FeatureRegistry can check if feature class is registered
 */
#[Group('region-builder')]
#[Group('feature-dependency-resolution')]
class IsRegisteredCheckTest extends TestCase
{
    public function testIsRegisteredReturnsTrueForRegisteredFeature(): void
    {
        $registry = new FeatureRegistry();

        $feature = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void {}
        };

        $registry->register($feature);

        $this->assertTrue(
            $registry->isRegistered($feature::class),
            'isRegistered should return true for registered feature'
        );
    }

    public function testIsRegisteredReturnsFalseForUnregisteredFeature(): void
    {
        $registry = new FeatureRegistry();

        $feature = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void {}
        };

        $this->assertFalse(
            $registry->isRegistered($feature::class),
            'isRegistered should return false for unregistered feature'
        );
    }
}
