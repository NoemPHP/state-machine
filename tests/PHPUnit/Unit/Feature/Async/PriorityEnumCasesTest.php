<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\TestCase;

/**
 * Validates Priority enum cases
 *
 * Acceptance Criteria: Priority enum defines LOW, NORMAL, and HIGH cases
 * Intent: Provides type-safe priority levels preventing invalid priority values
 */
final class PriorityEnumCasesTest extends TestCase
{
    public function testPriorityDefinesLowCase(): void
    {
        $this->assertTrue(defined(Priority::class . '::LOW'));
        $this->assertInstanceOf(Priority::class, Priority::LOW);
    }

    public function testPriorityDefinesNormalCase(): void
    {
        $this->assertTrue(defined(Priority::class . '::NORMAL'));
        $this->assertInstanceOf(Priority::class, Priority::NORMAL);
    }

    public function testPriorityDefinesHighCase(): void
    {
        $this->assertTrue(defined(Priority::class . '::HIGH'));
        $this->assertInstanceOf(Priority::class, Priority::HIGH);
    }
}
