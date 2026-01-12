<?php

declare(strict_types=1);

namespace Noem\Tests\PHPUnit\Unit\Feature\Async\Yaml;

use PHPUnit\Framework\TestCase;

class InvalidPriorityTest extends TestCase
{
    /**
     * @dataProvider invalidPriorityProvider
     */
    public function testInvalidPriorityValuesThrowValidationErrors($invalidPriority)
    {
        $asyncConfigSchema = \Nette\Schema\Expect::structure([
            'priority' => \Nette\Schema\Expect::anyOf('low', 'normal', 'high'),
        ])->skipDefaults();

        $processor = new \Nette\Schema\Processor();

        $this->expectException(\Nette\Schema\ValidationException::class);
        $processor->process($asyncConfigSchema, [
            'priority' => $invalidPriority,
        ]);
    }

    public static function invalidPriorityProvider()
    {
        return [
            ['invalid'],
            ['medium'],
            ['critical'],
            ['urgent'],
            ['low-normal'],
            ['high priority'],
            [''],
            [null],
            [123],
            [[]],
            [new \stdClass()],
        ];
    }

    public function testValidationErrorMessageIsHelpful()
    {
        $asyncConfigSchema = \Nette\Schema\Expect::structure([
            'priority' => \Nette\Schema\Expect::anyOf('low', 'normal', 'high'),
        ])->skipDefaults();

        $processor = new \Nette\Schema\Processor();

        try {
            $processor->process($asyncConfigSchema, [
                'priority' => 'invalid_value',
            ]);
            $this->fail('Expected ValidationException was not thrown');
        } catch (\Nette\Schema\ValidationException $e) {
            // Verify error message contains helpful information
            $errorMessage = $e->getMessage();
            $this->assertStringContainsString('priority', $errorMessage);
            $this->assertStringContainsString('low', $errorMessage);
            $this->assertStringContainsString('normal', $errorMessage);
            $this->assertStringContainsString('high', $errorMessage);
        }
    }
}
