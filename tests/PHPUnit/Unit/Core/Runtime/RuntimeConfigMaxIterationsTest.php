<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RuntimeConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RuntimeConfig stores maxIterations with default value
 */
#[Group('runtime')]
#[Group('runtime-config')]
class RuntimeConfigMaxIterationsTest extends TestCase
{
    public function testMaxIterationsHasDefaultValue(): void
    {
        $config = new RuntimeConfig();

        $this->assertEquals(10000, $config->maxIterations, 'Default maxIterations should be 10000');
    }

    public function testMaxIterationsCanBeCustomized(): void
    {
        $config = new RuntimeConfig(maxIterations: 5000);

        $this->assertEquals(5000, $config->maxIterations, 'Custom maxIterations should be stored');
    }

    public function testMaxIterationsIsReadonly(): void
    {
        $config = new RuntimeConfig(maxIterations: 100);

        $this->assertEquals(100, $config->maxIterations);

        // Verify readonly by checking if property_exists and is not writable
        $reflection = new \ReflectionProperty(RuntimeConfig::class, 'maxIterations');
        $this->assertTrue($reflection->isReadOnly(), 'maxIterations should be readonly');
    }
}
