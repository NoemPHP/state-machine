<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\Feature;
use Noem\State\Feature\FeatureRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: FeatureRegistry ignores duplicate registration of same feature class
 */
#[Group('region-builder')]
#[Group('feature-dependency-resolution')]
class IgnoresDuplicatesTest extends TestCase
{
    public function testDuplicateRegistrationIsIgnored(): void
    {
        $registry = new FeatureRegistry();

        $feature = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void
            {
            }
        };

        $registry->register($feature);
        $registry->register($feature); // Duplicate

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $this->assertCount(
            1,
            $resolved,
            'Registry should contain only one instance despite duplicate registration'
        );
    }

    public function testSecondRegistrationDoesNotOverwriteFirst(): void
    {
        $registry = new FeatureRegistry();

        $firstInstance = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void
            {
            }
        };

        $registry->register($firstInstance);

        // Try to register same class again (though same instance in anonymous class case)
        $registry->register($firstInstance);

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $this->assertSame(
            $firstInstance,
            $resolved[0],
            'First registered instance should be preserved'
        );
    }
}
