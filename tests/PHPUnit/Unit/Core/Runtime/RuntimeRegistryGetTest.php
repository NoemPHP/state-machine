<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Region;
use Noem\State\Runtime;
use Noem\State\RuntimeRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RuntimeRegistry can retrieve Runtime by Region
 */
#[Group('runtime')]
#[Group('runtime-registry')]
class RuntimeRegistryGetTest extends TestCase
{
    public function testGetRetrievesRegisteredRuntime(): void
    {
        $region = $this->createMock(Region::class);
        $runtime = $this->createMock(Runtime::class);

        RuntimeRegistry::register($region, $runtime);

        $retrieved = RuntimeRegistry::get($region);

        $this->assertSame($runtime, $retrieved, 'get() should return registered runtime');
    }

    public function testGetReturnsCorrectRuntimeForMultipleRegistrations(): void
    {
        $region1 = $this->createMock(Region::class);
        $runtime1 = $this->createMock(Runtime::class);

        $region2 = $this->createMock(Region::class);
        $runtime2 = $this->createMock(Runtime::class);

        RuntimeRegistry::register($region1, $runtime1);
        RuntimeRegistry::register($region2, $runtime2);

        $this->assertSame($runtime1, RuntimeRegistry::get($region1));
        $this->assertSame($runtime2, RuntimeRegistry::get($region2));
        $this->assertNotSame(
            RuntimeRegistry::get($region1),
            RuntimeRegistry::get($region2),
            'Different regions should return different runtimes'
        );
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(RuntimeRegistry::class);
        $property = $reflection->getProperty('runtimes');
        $property->setValue(null, null);
    }
}
