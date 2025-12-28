<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Region;
use Noem\State\Runtime;
use Noem\State\RuntimeRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RuntimeRegistry can register Region-to-Runtime mapping
 */
#[Group('runtime')]
#[Group('runtime-registry')]
class RuntimeRegistryRegisterTest extends TestCase
{
    public function testCanRegisterRegionToRuntimeMapping(): void
    {
        $region = $this->createMock(Region::class);
        $runtime = $this->createMock(Runtime::class);

        // Should not throw
        RuntimeRegistry::register($region, $runtime);

        // Verify it was stored
        $retrieved = RuntimeRegistry::get($region);
        $this->assertSame($runtime, $retrieved, 'Registered runtime should be retrievable');
    }

    public function testCanRegisterMultipleRegions(): void
    {
        $region1 = $this->createMock(Region::class);
        $runtime1 = $this->createMock(Runtime::class);

        $region2 = $this->createMock(Region::class);
        $runtime2 = $this->createMock(Runtime::class);

        RuntimeRegistry::register($region1, $runtime1);
        RuntimeRegistry::register($region2, $runtime2);

        $this->assertSame($runtime1, RuntimeRegistry::get($region1));
        $this->assertSame($runtime2, RuntimeRegistry::get($region2));
    }

    public function testReregisteringRegionReplacesRuntime(): void
    {
        $region = $this->createMock(Region::class);
        $runtime1 = $this->createMock(Runtime::class);
        $runtime2 = $this->createMock(Runtime::class);

        RuntimeRegistry::register($region, $runtime1);
        RuntimeRegistry::register($region, $runtime2);

        $retrieved = RuntimeRegistry::get($region);
        $this->assertSame($runtime2, $retrieved, 'Second registration should replace first');
    }

    protected function tearDown(): void
    {
        // Clean up registry between tests
        $reflection = new \ReflectionClass(RuntimeRegistry::class);
        $property = $reflection->getProperty('runtimes');
        $property->setValue(null, null);
    }
}
