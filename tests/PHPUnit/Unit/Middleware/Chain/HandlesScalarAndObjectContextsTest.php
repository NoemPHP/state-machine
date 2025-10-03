<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chain properly handles both scalar and object contexts
 */
#[Group('middleware')]
#[Group('chain')]
class HandlesScalarAndObjectContextsTest extends TestCase
{
    public function testHandlesStringContext(): void
    {
        $chain = new Chain(fn($c) => strtoupper($c));

        $result = $chain->call('hello');

        $this->assertEquals('HELLO', $result);
    }

    public function testHandlesIntegerContext(): void
    {
        $chain = new Chain(fn($c) => $c * 2);

        $result = $chain->call(21);

        $this->assertEquals(42, $result);
    }

    public function testHandlesArrayContext(): void
    {
        $middleware = fn($c, $next) => $next(array_merge($c, ['added' => true]));

        $chain = new Chain(fn($c) => $c, [$middleware]);

        $result = $chain->call(['original' => true]);

        $this->assertEquals(['original' => true, 'added' => true], $result);
    }

    public function testHandlesObjectContext(): void
    {
        $middleware = function ($context, $next) {
            $context->modified = true;
            return $next($context);
        };

        $obj = new \stdClass();
        $obj->value = 'test';

        $chain = new Chain(fn($c) => $c, [$middleware]);

        $result = $chain->call($obj);

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertEquals('test', $result->value);
        $this->assertTrue($result->modified);
    }

    public function testHandlesBooleanContext(): void
    {
        $chain = new Chain(fn($c) => !$c);

        $this->assertFalse($chain->call(true));
        $this->assertTrue($chain->call(false));
    }

    public function testHandlesNullContext(): void
    {
        $chain = new Chain(fn($c) => $c ?? 'default');

        $result = $chain->call(null);

        $this->assertEquals('default', $result);
    }

    public function testHandlesCustomObjectContext(): void
    {
        $context = new class {
            public int $value = 10;
            public function double(): void
            {
                $this->value *= 2;
            }
        };

        $middleware = function ($c, $next) {
            $c->double();
            return $next($c);
        };

        $chain = new Chain(fn($c) => $c, [$middleware]);

        $result = $chain->call($context);

        $this->assertEquals(20, $result->value);
    }
}
