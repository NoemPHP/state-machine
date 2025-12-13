<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\TestCase;

/**
 * Validates AsyncConfig immutability
 *
 * Acceptance Criteria: AsyncConfig is immutable with readonly properties
 * Intent: Prevents configuration modification after creation, ensuring predictable async behavior
 */
final class AsyncConfigImmutabilityTest extends TestCase
{
    public function testAsyncConfigPropertiesAreReadonly(): void
    {
        $config = new AsyncConfig(
            debounce: 0.5,
            throttle: 1.0,
            singleton: true,
            priority: Priority::HIGH,
            timeout: 5.0
        );

        $reflection = new \ReflectionClass($config);

        foreach (['debounce', 'throttle', 'singleton', 'priority', 'timeout'] as $property) {
            $prop = $reflection->getProperty($property);
            $this->assertTrue(
                $prop->isReadOnly(),
                "Property {$property} should be readonly"
            );
        }
    }

    public function testAsyncConfigCannotBeModifiedAfterCreation(): void
    {
        $config = new AsyncConfig(debounce: 0.5);

        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/Cannot modify readonly property/');

        // @phpstan-ignore-next-line - Intentionally trying to modify readonly property
        $config->debounce = 1.0;
    }
}
