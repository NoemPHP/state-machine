<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: quickRegion creates region from state array
 */
#[Group('loader'), Group('holon'), Group('holon-convenience')]
class QuickRegionTest extends TestCase
{
    public function testCreatesRegionFromStateArray(): void
    {
        // Arrange
        $states = [
            ['name' => 'start', 'initial' => true],
            ['name' => 'end', 'final' => true],
        ];

        // Act
        $region = Holon::quickRegion($states);

        // Assert
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('start', $region->currentState());
        $this->assertFalse($region->isFinal());
    }

    public function testSupportsSimpleStateDefinitions(): void
    {
        // Arrange
        $states = [
            [
                'name' => 'idle',
                'initial' => true,
                'transitions' => [
                    ['target' => 'active']
                ]
            ],
            [
                'name' => 'active',
                'transitions' => [
                    ['target' => 'done']
                ]
            ],
            [
                'name' => 'done',
                'final' => true
            ],
        ];

        // Act
        $region = Holon::quickRegion($states);

        // Assert
        $this->assertEquals('idle', $region->currentState());

        // Trigger transitions
        $region->trigger(new \stdClass());
        $this->assertEquals('active', $region->currentState());

        $region->trigger(new \stdClass());
        $this->assertEquals('done', $region->currentState());
        $this->assertTrue($region->isFinal());
    }

    public function testWorksWithMinimalConfiguration(): void
    {
        // Arrange
        $states = [
            ['name' => 'only', 'initial' => true, 'final' => true],
        ];

        // Act
        $region = Holon::quickRegion($states);

        // Assert
        $this->assertEquals('only', $region->currentState());
        $this->assertTrue($region->isFinal());
    }

    public function testProvidesBackwardCompatibility(): void
    {
        // Arrange - this pattern should work just like the old way
        $states = [
            ['name' => 'a', 'initial' => true],
            ['name' => 'b', 'final' => true],
        ];

        // Act
        $region = Holon::quickRegion($states);

        // Assert - basic region created successfully
        $this->assertInstanceOf(Region::class, $region);
        $this->assertNotNull($region->currentState());
    }
}
