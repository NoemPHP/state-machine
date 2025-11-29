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
        // This test will use named classes since we need mutual references
        // which anonymous classes can't express directly

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/circular.*dependenc/i');

        // We'll create the circular dependency in the implementation
        // For now, this is a placeholder that will fail until implementation
        $this->markTestIncomplete(
            'Circular dependency detection requires named test fixture classes'
        );
    }

    public function testIndirectCircularDependencyThrowsException(): void
    {
        // A → B → C → A creates a cycle

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/circular.*dependenc/i');

        $this->markTestIncomplete(
            'Circular dependency detection requires named test fixture classes'
        );
    }
}
