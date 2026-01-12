<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Definition;

use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criteria: RegionPresentation is immutable after construction
 * Intent: Prevents modification of presentation contracts after registration, ensuring predictable behavior
 * Criticality: constraint
 */
final class ImmutabilityTest extends TestCase
{
    public function testAllPropertiesAreReadonly(): void
    {
        $reflection = new ReflectionClass(RegionPresentation::class);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Property '{$property->getName()}' is not readonly"
            );
        }
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new ReflectionClass(RegionPresentation::class);

        // PHP 8.2+ readonly class check
        $this->assertTrue(
            $reflection->isReadOnly(),
            'RegionPresentation class should be marked as readonly'
        );
    }
}
