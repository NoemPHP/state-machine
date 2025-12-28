<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Region;
use Noem\State\RuntimeConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RuntimeConfig accepts optional triggerFactory callable
 */
#[Group('runtime')]
#[Group('runtime-config')]
class RuntimeConfigTriggerFactoryTest extends TestCase
{
    public function testTriggerFactoryDefaultsToNull(): void
    {
        $config = new RuntimeConfig();

        $this->assertNull($config->triggerFactory, 'triggerFactory should default to null');
    }

    public function testTriggerFactoryCanBeProvided(): void
    {
        $factory = function (int $iteration, Region $region): object {
            return (object)['iteration' => $iteration];
        };

        $config = new RuntimeConfig(triggerFactory: $factory);

        $this->assertSame($factory, $config->triggerFactory, 'triggerFactory should be stored');
    }

    public function testTriggerFactoryIsCallable(): void
    {
        $factory = fn(int $iteration, Region $region): object => (object)['test' => true];

        $config = new RuntimeConfig(triggerFactory: $factory);

        $this->assertIsCallable($config->triggerFactory, 'triggerFactory should be callable');
    }

    public function testTriggerFactoryIsReadonly(): void
    {
        $factory = fn(int $i, Region $r): object => (object)[];
        $config = new RuntimeConfig(triggerFactory: $factory);

        $reflection = new \ReflectionProperty(RuntimeConfig::class, 'triggerFactory');
        $this->assertTrue($reflection->isReadOnly(), 'triggerFactory should be readonly');
    }
}
