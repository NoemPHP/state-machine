<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use PHPUnit\Framework\TestCase;

/**
 * Validates AsyncConfig debounce validation
 *
 * Acceptance Criteria: AsyncConfig validates debounce is non-negative
 * Intent: Prevents invalid configuration, ensuring debounce values make semantic sense
 */
final class DebounceValidationTest extends TestCase
{
    public function testDebounceRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Debounce must be >= 0');

        new AsyncConfig(debounce: -0.5);
    }

    public function testDebounceAcceptsZero(): void
    {
        $config = new AsyncConfig(debounce: 0.0);

        $this->assertSame(0.0, $config->debounce);
    }

    public function testDebounceAcceptsPositiveValue(): void
    {
        $config = new AsyncConfig(debounce: 0.5);

        $this->assertSame(0.5, $config->debounce);
    }
}
