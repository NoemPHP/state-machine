<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RuntimeConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RuntimeConfig accepts 0 or -1 for maxIterations to disable iteration limit
 */
#[Group('runtime')]
#[Group('runtime-config')]
class RuntimeConfigUnlimitedIterationsTest extends TestCase
{
    public function testAcceptsZeroForUnlimitedIterations(): void
    {
        $config = new RuntimeConfig(maxIterations: 0);

        $this->assertEquals(0, $config->maxIterations, 'maxIterations should accept 0');
    }

    public function testAcceptsNegativeOneForUnlimitedIterations(): void
    {
        $config = new RuntimeConfig(maxIterations: -1);

        $this->assertEquals(-1, $config->maxIterations, 'maxIterations should accept -1');
    }

    public function testZeroIndicatesUnlimitedExecution(): void
    {
        $config = new RuntimeConfig(maxIterations: 0);

        $this->assertLessThanOrEqual(
            0,
            $config->maxIterations,
            'Zero or negative maxIterations indicates unlimited execution'
        );
    }

    public function testNegativeOneIndicatesUnlimitedExecution(): void
    {
        $config = new RuntimeConfig(maxIterations: -1);

        $this->assertLessThanOrEqual(
            0,
            $config->maxIterations,
            'Zero or negative maxIterations indicates unlimited execution'
        );
    }

    public function testUnlimitedIterationsIsReadonly(): void
    {
        $config = new RuntimeConfig(maxIterations: 0);

        $this->assertEquals(0, $config->maxIterations);

        // Verify readonly property
        $reflection = new \ReflectionProperty(RuntimeConfig::class, 'maxIterations');
        $this->assertTrue($reflection->isReadOnly(), 'maxIterations should be readonly');
    }
}
