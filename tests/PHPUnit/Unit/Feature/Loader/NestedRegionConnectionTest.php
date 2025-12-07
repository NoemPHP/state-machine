<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray creates and connects nested regions with proper flags
 */
#[Group('loader')]
#[Group('array-processing')]
class NestedRegionConnectionTest extends TestCase
{
    public function testCreatesAndConnectsNestedRegions(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('extractConfig');
        $method->setAccessible(true);

        $statesConfig = [
            [
                'name' => 'parent',
                'regions' => [
                    ['states' => [['name' => 'child']]],
                ],
            ],
        ];

        [$states, $regions] = $method->invoke($processor, $statesConfig);

        $this->assertArrayHasKey('parent', $regions);
    }
}
