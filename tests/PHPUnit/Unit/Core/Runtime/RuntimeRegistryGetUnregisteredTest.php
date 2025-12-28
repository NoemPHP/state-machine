<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Region;
use Noem\State\RuntimeRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RuntimeRegistry returns null for unregistered Region
 */
#[Group('runtime')]
#[Group('runtime-registry')]
class RuntimeRegistryGetUnregisteredTest extends TestCase
{
    public function testGetReturnsNullForUnregisteredRegion(): void
    {
        $region = $this->createMock(Region::class);

        $result = RuntimeRegistry::get($region);

        $this->assertNull($result, 'get() should return null for unregistered region');
    }

    public function testGetReturnsNullAfterUnregister(): void
    {
        $region = $this->createMock(Region::class);
        $runtime = $this->createMock(\Noem\State\Runtime::class);

        RuntimeRegistry::register($region, $runtime);
        RuntimeRegistry::unregister($region);

        $result = RuntimeRegistry::get($region);

        $this->assertNull($result, 'get() should return null after unregister');
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(RuntimeRegistry::class);
        $property = $reflection->getProperty('runtimes');
        $property->setValue(null, null);
    }
}
