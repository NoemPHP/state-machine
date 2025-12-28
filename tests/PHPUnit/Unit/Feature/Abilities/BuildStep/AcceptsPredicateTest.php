<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\BuildStep;

use Noem\State\Feature\Abilities\BuildStep\RegisterAbility;
use Noem\State\Region;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Abilities\BuildStep\RegisterAbility
 */
final class AcceptsPredicateTest extends TestCase
{
    public function testAcceptsOptionalPredicateCallable(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];
        $predicate = fn(Region $region) => true;

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler,
            predicate: $predicate
        );

        $reflection = new \ReflectionClass($step);
        $predicateProperty = $reflection->getProperty('predicate');

        $this->assertSame(
            $predicate,
            $predicateProperty->getValue($step),
            'RegisterAbility must store optional predicate callable'
        );
    }

    public function testPredicateDefaultsToNull(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler
        );

        $reflection = new \ReflectionClass($step);
        $predicateProperty = $reflection->getProperty('predicate');

        $this->assertNull(
            $predicateProperty->getValue($step),
            'Predicate must default to null when not provided'
        );
    }
}
