<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RuntimeConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RuntimeConfig accepts optional onComplete callable
 */
#[Group('runtime')]
#[Group('runtime-config')]
class RuntimeConfigOnCompleteTest extends TestCase
{
    public function testOnCompleteDefaultsToNull(): void
    {
        $config = new RuntimeConfig();

        $this->assertNull($config->onComplete, 'onComplete should default to null');
    }

    public function testOnCompleteCanBeProvided(): void
    {
        $callback = function (): void {
            // Completion logic
        };

        $config = new RuntimeConfig(onComplete: $callback);

        $this->assertSame($callback, $config->onComplete, 'onComplete should be stored');
    }

    public function testOnCompleteIsCallable(): void
    {
        $callback = fn() => null;

        $config = new RuntimeConfig(onComplete: $callback);

        $this->assertIsCallable($config->onComplete, 'onComplete should be callable');
    }

    public function testOnCompleteIsReadonly(): void
    {
        $callback = fn() => null;
        $config = new RuntimeConfig(onComplete: $callback);

        $reflection = new \ReflectionProperty(RuntimeConfig::class, 'onComplete');
        $this->assertTrue($reflection->isReadOnly(), 'onComplete should be readonly');
    }
}
