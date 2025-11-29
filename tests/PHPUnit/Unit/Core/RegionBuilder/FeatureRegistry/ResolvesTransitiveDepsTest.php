<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\FeatureRegistry;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\TransitiveTestFeatureA;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\TransitiveTestFeatureB;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\TransitiveTestFeatureC;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: FeatureRegistry resolves transitive dependencies recursively
 */
#[Group('region-builder')]
#[Group('feature-dependency-resolution')]
class ResolvesTransitiveDepsTest extends TestCase
{
    public function testResolvesTransitiveDependencies(): void
    {
        $registry = new FeatureRegistry();
        $registry->register(new TransitiveTestFeatureC()); // Only register C

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $this->assertCount(
            3,
            $resolved,
            'Registry should resolve all transitive dependencies (C → B → A)'
        );

        $classNames = array_map(fn($f) => $f::class, $resolved);

        $this->assertContains(TransitiveTestFeatureA::class, $classNames, 'Transitive dependency A should be resolved');
        $this->assertContains(TransitiveTestFeatureB::class, $classNames, 'Direct dependency B should be resolved');
        $this->assertContains(TransitiveTestFeatureC::class, $classNames, 'Root feature C should be resolved');
    }

    public function testResolvesDeepTransitiveDependencies(): void
    {
        // Use existing 3-level chain: A → B → C
        $registry = new FeatureRegistry();
        $registry->register(new TransitiveTestFeatureC()); // Only register the leaf

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $this->assertCount(
            3,
            $resolved,
            'Registry should resolve deep transitive dependencies (C → B → A)'
        );
    }
}
