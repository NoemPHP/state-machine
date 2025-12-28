<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\BuildStep;

use Noem\State\Feature\Abilities\BuildStep\RegisterAbility;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Abilities\BuildStep\RegisterAbility
 */
final class AcceptsParameterSchemaTest extends TestCase
{
    public function testAcceptsOptionalParameterSchema(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
            'required' => ['name'],
        ];

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler,
            parameterSchema: $schema
        );

        $reflection = new \ReflectionClass($step);
        $schemaProperty = $reflection->getProperty('parameterSchema');

        $this->assertEquals(
            $schema,
            $schemaProperty->getValue($step),
            'RegisterAbility must store optional parameter schema'
        );
    }

    public function testParameterSchemaDefaultsToEmptyArray(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler
        );

        $reflection = new \ReflectionClass($step);
        $schemaProperty = $reflection->getProperty('parameterSchema');

        $this->assertEquals(
            [],
            $schemaProperty->getValue($step),
            'Parameter schema must default to empty array when not provided'
        );
    }
}
