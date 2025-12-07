<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionSpawnRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionSpawnRegistry initializes with empty records array
 */
#[Group('loader')]
#[Group('spawn-registry')]
class SpawnRegistryInitializationTest extends TestCase
{
    public function testRegistryInitializesWithEmptyRecords(): void
    {
        $registry = new RegionSpawnRegistry();

        $this->assertIsArray($registry->records);
        $this->assertEmpty($registry->records);
        $this->assertCount(0, $registry->records);
    }
}
