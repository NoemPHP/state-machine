<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\BuildStep;

use Noem\State\Feature\Abilities\BuildStep\RegisterAbility;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Abilities\BuildStep\RegisterAbility
 */
final class AcceptsHandlerTest extends TestCase
{
    public function testAcceptsHandlerCallable(): void
    {
        $handler = fn(array $params) => ['sum' => array_sum($params['numbers'])];

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler
        );

        $reflection = new \ReflectionClass($step);
        $handlerProperty = $reflection->getProperty('handler');

        $this->assertSame(
            $handler,
            $handlerProperty->getValue($step),
            'RegisterAbility must store handler callable'
        );
    }

    public function testHandlerIsReadonly(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler
        );

        $reflection = new \ReflectionClass($step);
        $handlerProperty = $reflection->getProperty('handler');

        $this->assertTrue(
            $handlerProperty->isReadOnly(),
            'Handler property must be readonly'
        );
    }
}
