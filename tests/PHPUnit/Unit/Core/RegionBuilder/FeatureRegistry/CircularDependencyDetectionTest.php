<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\FeatureRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Topological sort detects circular dependencies
 */
#[Group('region-builder')]
#[Group('feature-dependency-resolution')]
class CircularDependencyDetectionTest extends TestCase
{
    public function testDetectsDirectCircularDependency(): void
    {
        // CircularFeatureA requires CircularFeatureB
        // CircularFeatureB requires CircularFeatureA
        // This creates a direct circular dependency: A → B → A

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Circular feature dependencies detected');

        $registry = new FeatureRegistry();
        $registry->register(new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureA());
        $registry->register(new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureB());

        $chainMail = new ChainMail();

        // Should throw LogicException during resolve when circular dependency is detected
        $registry->resolve($chainMail);
    }

    public function testDetectsIndirectCircularDependency(): void
    {
        // CircularFeatureIndirectA requires CircularFeatureIndirectB
        // CircularFeatureIndirectB requires CircularFeatureC
        // CircularFeatureC requires CircularFeatureIndirectA
        // This creates an indirect circular dependency: A → B → C → A

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Circular feature dependencies detected');

        $registry = new FeatureRegistry();
        $registry->register(new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureIndirectA());
        $registry->register(new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureIndirectB());
        $registry->register(new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureC());

        $chainMail = new ChainMail();

        // Should throw LogicException during resolve when circular dependency is detected
        $registry->resolve($chainMail);
    }

    public function testDetectsSelfDependency(): void
    {
        // CircularFeatureSelf depends on itself
        // This creates a self-referential circular dependency

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Circular feature dependencies detected');

        $registry = new FeatureRegistry();
        $registry->register(new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureSelf());

        $chainMail = new ChainMail();

        // Should throw LogicException during resolve when circular dependency is detected
        $registry->resolve($chainMail);
    }
}
