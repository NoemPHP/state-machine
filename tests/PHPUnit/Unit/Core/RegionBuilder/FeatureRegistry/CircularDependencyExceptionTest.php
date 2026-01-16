<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\FeatureRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Topological sort throws LogicException for circular dependencies
 */
#[Group('region-builder')]
#[Group('feature-dependency-resolution')]
class CircularDependencyExceptionTest extends TestCase
{
    public function testThrowsLogicExceptionForCircularDependency(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/circular.*depend/i');

        $registry = new FeatureRegistry();
        $registry->register(new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureA());
        $registry->register(new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureB());

        $chainMail = new ChainMail();

        // Should throw LogicException with message matching pattern
        $registry->resolve($chainMail);
    }

    public function testExceptionMessageDescribesCycle(): void
    {
        // Exception message is "Circular feature dependencies detected"
        // This test verifies the message provides useful information about the error

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Circular feature dependencies detected');

        $registry = new FeatureRegistry();
        $registry->register(new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureIndirectA());
        $registry->register(new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureIndirectB());
        $registry->register(new \Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\CircularFeatureC());

        $chainMail = new ChainMail();

        // Should throw LogicException with descriptive message
        $registry->resolve($chainMail);
    }
}
