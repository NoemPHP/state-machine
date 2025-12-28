<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\BuildStep;

use Noem\State\Feature\Abilities\BuildStep\RegisterAbility;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Abilities\BuildStep\RegisterAbility
 */
final class AcceptsResponseSchemaTest extends TestCase
{
    public function testAcceptsOptionalResponseSchema(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];
        $schema = [
            'type' => 'object',
            'properties' => [
                'result' => ['type' => 'string'],
            ],
        ];

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler,
            responseSchema: $schema
        );

        $reflection = new \ReflectionClass($step);
        $schemaProperty = $reflection->getProperty('responseSchema');

        $this->assertEquals(
            $schema,
            $schemaProperty->getValue($step),
            'RegisterAbility must store optional response schema'
        );
    }

    public function testResponseSchemaDefaultsToEmptyArray(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler
        );

        $reflection = new \ReflectionClass($step);
        $schemaProperty = $reflection->getProperty('responseSchema');

        $this->assertEquals(
            [],
            $schemaProperty->getValue($step),
            'Response schema must default to empty array when not provided'
        );
    }
}
