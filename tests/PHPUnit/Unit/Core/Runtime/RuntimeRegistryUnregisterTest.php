<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Region;
use Noem\State\Runtime;
use Noem\State\RuntimeRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RuntimeRegistry can unregister Region-to-Runtime mapping
 */
#[Group('runtime')]
#[Group('runtime-registry')]
class RuntimeRegistryUnregisterTest extends TestCase
{
    public function testUnregisterRemovesMapping(): void
    {
        $region = $this->createMock(Region::class);
        $runtime = $this->createMock(Runtime::class);

        RuntimeRegistry::register($region, $runtime);
        $this->assertSame($runtime, RuntimeRegistry::get($region));

        RuntimeRegistry::unregister($region);

        $this->assertNull(RuntimeRegistry::get($region), 'Region should not be retrievable after unregister');
    }

    public function testUnregisterDoesNotAffectOtherMappings(): void
    {
        $region1 = $this->createMock(Region::class);
        $runtime1 = $this->createMock(Runtime::class);

        $region2 = $this->createMock(Region::class);
        $runtime2 = $this->createMock(Runtime::class);

        RuntimeRegistry::register($region1, $runtime1);
        RuntimeRegistry::register($region2, $runtime2);

        RuntimeRegistry::unregister($region1);

        $this->assertNull(RuntimeRegistry::get($region1), 'Unregistered region should return null');
        $this->assertSame($runtime2, RuntimeRegistry::get($region2), 'Other registrations should remain');
    }

    public function testUnregisterUnregisteredRegionDoesNotThrow(): void
    {
        $region = $this->createMock(Region::class);

        // Should not throw
        RuntimeRegistry::unregister($region);

        $this->assertTrue(true, 'Unregistering unregistered region should not throw');
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(RuntimeRegistry::class);
        $property = $reflection->getProperty('runtimes');
        $property->setValue(null, null);
    }
}
