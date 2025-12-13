<?php

declare(strict_types=1);

namespace Noem\Tests\PHPUnit\Unit\Feature\Loader;

use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\TestCase;

class PriorityConversionTest extends TestCase
{
    /**
     * @dataProvider priorityStringProvider
     */
    public function testPriorityStringConvertsToPriorityEnum($string, $expectedEnum)
    {
        $converter = new class {
            public function convertPriority(string $priority): Priority
            {
                return match(strtolower($priority)) {
                    'low' => Priority::LOW,
                    'high' => Priority::HIGH,
                    default => Priority::NORMAL,
                };
            }
        };

        $result = $converter->convertPriority($string);
        $this->assertEquals($expectedEnum, $result);
    }

    public static function priorityStringProvider()
    {
        return [
            ['low', Priority::LOW],
            ['normal', Priority::NORMAL],
            ['high', Priority::HIGH],
            ['NORMAL', Priority::NORMAL],  // Case insensitive
            ['HIGH', Priority::HIGH],    // Case insensitive
            ['LOW', Priority::LOW],      // Case insensitive
            ['invalid', Priority::NORMAL], // Default to normal for invalid values
        ];
    }

    public function testPriorityConversionIsCaseInsensitive()
    {
        $converter = new class {
            public function convertPriority(string $priority): Priority
            {
                return match(strtolower($priority)) {
                    'low' => Priority::LOW,
                    'high' => Priority::HIGH,
                    default => Priority::NORMAL,
                };
            }
        };

        $this->assertEquals(Priority::LOW, $converter->convertPriority('low'));
        $this->assertEquals(Priority::LOW, $converter->convertPriority('LOW'));
        $this->assertEquals(Priority::LOW, $converter->convertPriority('Low'));

        $this->assertEquals(Priority::HIGH, $converter->convertPriority('high'));
        $this->assertEquals(Priority::HIGH, $converter->convertPriority('HIGH'));
        $this->assertEquals(Priority::HIGH, $converter->convertPriority('High'));

        $this->assertEquals(Priority::NORMAL, $converter->convertPriority('normal'));
        $this->assertEquals(Priority::NORMAL, $converter->convertPriority('NORMAL'));
        $this->assertEquals(Priority::NORMAL, $converter->convertPriority('Normal'));
    }

    public function testUnknownPriorityDefaultsToNormal()
    {
        $converter = new class {
            public function convertPriority(string $priority): Priority
            {
                return match(strtolower($priority)) {
                    'low' => Priority::LOW,
                    'high' => Priority::HIGH,
                    default => Priority::NORMAL,
                };
            }
        };

        $this->assertEquals(Priority::NORMAL, $converter->convertPriority('unknown'));
        $this->assertEquals(Priority::NORMAL, $converter->convertPriority('medium'));
        $this->assertEquals(Priority::NORMAL, $converter->convertPriority('critical'));
    }

    public function testPriorityEnumValues()
    {
        // Verify enum has expected backing values
        $this->assertEquals(1, Priority::LOW->value);
        $this->assertEquals(5, Priority::NORMAL->value);
        $this->assertEquals(10, Priority::HIGH->value);
    }

    public function testPriorityEnumOrdering()
    {
        // Verify enum ordering
        $this->assertTrue(Priority::LOW->value < Priority::NORMAL->value);
        $this->assertTrue(Priority::NORMAL->value < Priority::HIGH->value);
        $this->assertTrue(Priority::LOW->value < Priority::HIGH->value);
    }
}