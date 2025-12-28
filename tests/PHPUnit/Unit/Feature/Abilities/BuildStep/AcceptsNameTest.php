<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\BuildStep;

use Noem\State\Feature\Abilities\BuildStep\RegisterAbility;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Abilities\BuildStep\RegisterAbility
 */
final class AcceptsNameTest extends TestCase
{
    public function testAcceptsNameParameter(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];

        $step = new RegisterAbility(
            name: 'calculate-total',
            handler: $handler
        );

        $reflection = new \ReflectionClass($step);
        $nameProperty = $reflection->getProperty('name');

        $this->assertEquals(
            'calculate-total',
            $nameProperty->getValue($step),
            'RegisterAbility must store name parameter'
        );
    }

    public function testNameIsReadonly(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler
        );

        $reflection = new \ReflectionClass($step);
        $nameProperty = $reflection->getProperty('name');

        $this->assertTrue(
            $nameProperty->isReadOnly(),
            'Name property must be readonly'
        );
    }
}
