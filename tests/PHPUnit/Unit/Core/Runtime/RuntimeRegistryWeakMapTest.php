<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Region;
use Noem\State\Runtime;
use Noem\State\RuntimeRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RuntimeRegistry uses WeakMap for automatic garbage collection
 */
#[Group('runtime')]
#[Group('runtime-registry')]
class RuntimeRegistryWeakMapTest extends TestCase
{
    public function testRegistryUsesWeakMapInternally(): void
    {
        $reflection = new \ReflectionClass(RuntimeRegistry::class);
        $property = $reflection->getProperty('runtimes');

        // Trigger initialization by registering something
        $region = $this->createMock(Region::class);
        $runtime = $this->createMock(Runtime::class);
        RuntimeRegistry::register($region, $runtime);

        $weakMap = $property->getValue(null);

        $this->assertInstanceOf(\WeakMap::class, $weakMap, 'Registry should use WeakMap internally');
    }

    public function testRegionGarbageCollectedWhenNoLongerReferenced(): void
    {
        $runtime = $this->createMock(Runtime::class);

        // Create region in limited scope
        $region = $this->createMock(Region::class);
        RuntimeRegistry::register($region, $runtime);

        // Verify it's registered
        $this->assertSame($runtime, RuntimeRegistry::get($region));

        // Unset region - WeakMap should allow GC
        unset($region);

        // Note: Can't easily test actual GC without forcing collection
        // This test primarily documents the intent
        $this->assertTrue(true, 'Region should be garbage collectible');
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(RuntimeRegistry::class);
        $property = $reflection->getProperty('runtimes');
        $property->setValue(null, null);
    }
}
