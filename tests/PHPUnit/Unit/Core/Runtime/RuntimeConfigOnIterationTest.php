<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Region;
use Noem\State\RuntimeConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RuntimeConfig accepts optional onIteration callable
 */
#[Group('runtime')]
#[Group('runtime-config')]
class RuntimeConfigOnIterationTest extends TestCase
{
    public function testOnIterationDefaultsToNull(): void
    {
        $config = new RuntimeConfig();

        $this->assertNull($config->onIteration, 'onIteration should default to null');
    }

    public function testOnIterationCanBeProvided(): void
    {
        $callback = function (Region $region, object $trigger, int $iteration): void {
            // Callback logic
        };

        $config = new RuntimeConfig(onIteration: $callback);

        $this->assertSame($callback, $config->onIteration, 'onIteration should be stored');
    }

    public function testOnIterationIsCallable(): void
    {
        $callback = fn(Region $r, object $t, int $i) => null;

        $config = new RuntimeConfig(onIteration: $callback);

        $this->assertIsCallable($config->onIteration, 'onIteration should be callable');
    }

    public function testOnIterationIsReadonly(): void
    {
        $callback = fn(Region $r, object $t, int $i) => null;
        $config = new RuntimeConfig(onIteration: $callback);

        $reflection = new \ReflectionProperty(RuntimeConfig::class, 'onIteration');
        $this->assertTrue($reflection->isReadOnly(), 'onIteration should be readonly');
    }
}
