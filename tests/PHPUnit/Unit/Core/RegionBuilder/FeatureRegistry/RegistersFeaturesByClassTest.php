<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\Feature;
use Noem\State\Feature\FeatureRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: FeatureRegistry registers feature instances by class name
 */
#[Group('region-builder')]
#[Group('feature-dependency-resolution')]
class RegistersFeaturesByClassTest extends TestCase
{
    public function testRegisterStoresFeatureByClassName(): void
    {
        $registry = new FeatureRegistry();

        $feature = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void
            {
            }
        };

        $registry->register($feature);

        $this->assertTrue(
            $registry->isRegistered($feature::class),
            'Feature should be registered by its class name'
        );
    }

    public function testMultipleFeaturesCanBeRegistered(): void
    {
        $registry = new FeatureRegistry();

        $feature1 = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void
            {
            }
        };

        $feature2 = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void
            {
            }
        };

        $registry->register($feature1);
        $registry->register($feature2);

        $this->assertTrue($registry->isRegistered($feature1::class));
        $this->assertTrue($registry->isRegistered($feature2::class));
    }
}
