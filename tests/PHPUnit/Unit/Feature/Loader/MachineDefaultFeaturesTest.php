<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Machine;
use Noem\State\Feature\Loader\RegionLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Machine features method returns array with RegionLoader
 */
#[Group('loader')]
#[Group('machine-abstraction')]
class MachineDefaultFeaturesTest extends TestCase
{
    public function testFeaturesMethodReturnsArrayWithRegionLoader(): void
    {
        $machine = new class extends Machine {
            public function yaml(): string
            {
                return 'states: [{name: test}]';
            }

            public function trigger(): object
            {
                return new \stdClass();
            }
        };

        $features = $machine->features();

        $this->assertIsIterable($features);

        $featuresArray = iterator_to_array($features);
        $this->assertCount(1, $featuresArray);
        $this->assertInstanceOf(RegionLoader::class, $featuresArray[0]);
    }
}
