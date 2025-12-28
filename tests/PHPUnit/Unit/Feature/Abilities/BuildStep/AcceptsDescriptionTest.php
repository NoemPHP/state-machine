<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\BuildStep;

use Noem\State\Feature\Abilities\BuildStep\RegisterAbility;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Abilities\BuildStep\RegisterAbility
 */
final class AcceptsDescriptionTest extends TestCase
{
    public function testAcceptsOptionalDescription(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler,
            description: 'Performs test operations'
        );

        $reflection = new \ReflectionClass($step);
        $descProperty = $reflection->getProperty('description');

        $this->assertEquals(
            'Performs test operations',
            $descProperty->getValue($step),
            'RegisterAbility must store optional description'
        );
    }

    public function testDescriptionDefaultsToEmpty(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler
        );

        $reflection = new \ReflectionClass($step);
        $descProperty = $reflection->getProperty('description');

        $this->assertEquals(
            '',
            $descProperty->getValue($step),
            'Description must default to empty string when not provided'
        );
    }
}
