<?php

declare(strict_types=1);

namespace Noem\Tests\PHPUnit\Unit\Feature\Async\Yaml;

use PHPUnit\Framework\TestCase;

class AsyncValueValidationTest extends TestCase
{
    /**
     * @dataProvider validNumericProvider
     */
    public function testValidNumericValuesAreAccepted($field, $value)
    {
        $asyncConfigSchema = \Nette\Schema\Expect::structure([
            'timeout' => \Nette\Schema\Expect::float(),
            'debounce' => \Nette\Schema\Expect::float(),
            'throttle' => \Nette\Schema\Expect::float(),
        ])->skipDefaults();

        $processor = new \Nette\Schema\Processor();
        $config = $processor->process($asyncConfigSchema, [
            $field => $value,
        ]);

        $this->assertEquals($value, (array)$config[$field]);
    }

    public static function validNumericProvider()
    {
        return [
            // timeout
            ['timeout', 30.0],
            ['timeout', 0.1],
            ['timeout', 3600],
            ['timeout', 1.5],

            // debounce
            ['debounce', 0.5],
            ['debounce', 0.1],
            ['debounce', 10],
            ['debounce', 2.75],

            // throttle
            ['throttle', 1.0],
            ['throttle', 0.2],
            ['throttle', 60],
            ['throttle', 5.25],
        ];
    }

    /**
     * @dataProvider invalidNumericProvider
     */
    public function testInvalidNumericValuesThrowValidationErrors($field, $invalidValue)
    {
        $asyncConfigSchema = \Nette\Schema\Expect::structure([
            'timeout' => \Nette\Schema\Expect::float(),
            'debounce' => \Nette\Schema\Expect::float(),
            'throttle' => \Nette\Schema\Expect::float(),
        ])->skipDefaults();

        $processor = new \Nette\Schema\Processor();

        $this->expectException(\Nette\Schema\ValidationException::class);
        $processor->process($asyncConfigSchema, [
            $field => $invalidValue,
        ]);
    }

    public static function invalidNumericProvider()
    {
        return [
            // timeout should be positive
            ['timeout', -1],
            ['timeout', 0],
            ['timeout', -10.5],

            // debounce should be non-negative
            ['debounce', -0.1],
            ['debounce', -5],
            ['debounce', -1.25],

            // throttle should be non-negative
            ['throttle', -0.5],
            ['throttle', -2],
            ['throttle', -3.75],
        ];
    }

    public function testBooleanFieldsAcceptOnlyBooleans()
    {
        $asyncConfigSchema = \Nette\Schema\Expect::structure([
            'enabled' => \Nette\Schema\Expect::bool(true),
            'singleton' => \Nette\Schema\Expect::bool(false),
        ])->skipDefaults();

        $processor = new \Nette\Schema\Processor();

        // Test valid boolean values
        $config = $processor->process($asyncConfigSchema, [
            'enabled' => true,
            'singleton' => false,
        ]);

        $this->assertTrue($config['enabled']);
        $this->assertFalse($config['singleton']);
    }

    /**
     * @dataProvider invalidBooleanProvider
     */
    public function testBooleanFieldsRejectNonBooleanValues($field, $invalidValue)
    {
        $asyncConfigSchema = \Nette\Schema\Expect::structure([
            'enabled' => \Nette\Schema\Expect::bool(true),
            'singleton' => \Nette\Schema\Expect::bool(false),
        ])->skipDefaults();

        $processor = new \Nette\Schema\Processor();

        $this->expectException(\Nette\Schema\ValidationException::class);
        $processor->process($asyncConfigSchema, [
            $field => $invalidValue,
        ]);
    }

    public static function invalidBooleanProvider()
    {
        return [
            ['enabled', 'true'],
            ['enabled', 'false'],
            ['enabled', 1],
            ['enabled', 0],
            ['enabled', '1'],
            ['enabled', '0'],
            ['enabled', []],
            ['enabled', null],
            ['singleton', 'true'],
            ['singleton', 'false'],
            ['singleton', 1],
            ['singleton', 0],
            ['singleton', []],
            ['singleton', null],
        ];
    }

    public function testMixedValidConfiguration()
    {
        $asyncConfigSchema = \Nette\Schema\Expect::structure([
            'enabled' => \Nette\Schema\Expect::bool(true),
            'priority' => \Nette\Schema\Expect::anyOf('low', 'normal', 'high'),
            'singleton' => \Nette\Schema\Expect::bool(false),
            'timeout' => \Nette\Schema\Expect::float(),
            'debounce' => \Nette\Schema\Expect::float(),
            'throttle' => \Nette\Schema\Expect::float(),
        ])->skipDefaults();

        $processor = new \Nette\Schema\Processor();
        $config = $processor->process($asyncConfigSchema, [
            'enabled' => true,
            'priority' => 'high',
            'singleton' => true,
            'timeout' => 30.0,
            'debounce' => 0.5,
            'throttle' => 1.0,
        ]);

        $this->assertTrue($config['enabled']);
        $this->assertEquals('high', $config['priority']);
        $this->assertTrue($config['singleton']);
        $this->assertEquals(30.0, $config['timeout']);
        $this->assertEquals(0.5, $config['debounce']);
        $this->assertEquals(1.0, $config['throttle']);
    }
}