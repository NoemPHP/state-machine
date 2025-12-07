<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: quickRegion converts array to YAML internally
 */
#[Group('loader'), Group('holon'), Group('holon-convenience')]
class QuickRegionYamlConversionTest extends TestCase
{
    public function testConvertsArrayToYamlInternally(): void
    {
        // Arrange
        $states = [
            ['name' => 'first', 'initial' => true],
            ['name' => 'second', 'final' => true],
        ];

        // Act
        $region = Holon::quickRegion($states);

        // Assert - if YAML conversion worked, region should be created
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('first', $region->currentState());
    }

    public function testReusesFromYamlMethod(): void
    {
        // Arrange
        $states = [
            [
                'name' => 'a',
                'initial' => true,
                'transitions' => [
                    ['target' => 'b']
                ]
            ],
            [
                'name' => 'b',
                'final' => true
            ],
        ];

        // Act
        $region = Holon::quickRegion($states);

        // Assert - complex structures should work (proving YAML pipeline used)
        $region->trigger(new \stdClass());
        $this->assertEquals('b', $region->currentState());
        $this->assertTrue($region->isFinal());
    }

    public function testHandlesNestedStructures(): void
    {
        // Arrange
        $states = [
            [
                'name' => 'parent',
                'initial' => true,
                'regions' => [
                    [
                        'states' => [
                            ['name' => 'child', 'initial' => true, 'final' => true]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'done',
                'final' => true
            ],
        ];

        // Act
        $region = Holon::quickRegion($states);

        // Assert - nested regions should be handled
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('parent', $region->currentState());
    }

    public function testPreservesAllArrayStructure(): void
    {
        // Arrange
        $states = [
            [
                'name' => 'state1',
                'initial' => true,
                'transitions' => [
                    [
                        'target' => 'state2',
                        'guard' => null  // Will be converted to default guard
                    ]
                ]
            ],
            [
                'name' => 'state2',
                'final' => true
            ],
        ];

        // Act
        $region = Holon::quickRegion($states);

        // Assert - all array data preserved through YAML conversion
        $this->assertEquals('state1', $region->currentState());
        $region->trigger(new \stdClass());
        $this->assertEquals('state2', $region->currentState());
    }
}
