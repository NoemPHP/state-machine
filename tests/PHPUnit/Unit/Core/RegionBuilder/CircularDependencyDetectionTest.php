<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\Feature;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Circular feature dependencies throw LogicException during build
 */
#[Group('region-builder')]
#[Group('feature-registration')]
class CircularDependencyDetectionTest extends TestCase
{
    public function testDirectCircularDependencyThrowsException(): void
    {
        // CircularFeatureA requires CircularFeatureB
        // CircularFeatureB requires CircularFeatureA
        // This creates a direct circular dependency: A -> B -> A

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/circular.*dependenc/i');

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureA(),
            new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureB()
        );

        // Should throw LogicException during build when circular dependency is detected
        $builder->setStates('initial')->build();
    }

    public function testIndirectCircularDependencyThrowsException(): void
    {
        // CircularFeatureIndirectA requires CircularFeatureIndirectB
        // CircularFeatureIndirectB requires CircularFeatureC
        // CircularFeatureC requires CircularFeatureIndirectA
        // This creates an indirect circular dependency: A → B → C → A

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/circular.*dependenc/i');

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureIndirectA(),
            new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureIndirectB(),
            new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureC()
        );

        // Should throw LogicException during build when circular dependency is detected
        $builder->setStates('initial')->build();
    }
}
