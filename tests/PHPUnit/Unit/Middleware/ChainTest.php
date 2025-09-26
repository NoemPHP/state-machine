<?php

declare(strict_types=1);

namespace Noem\State\Tests\Middleware;

use Noem\State\Middleware\Chain;
use Noem\State\Middleware\ChainException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('middleware')]
class ChainTest extends TestCase
{

    public function testInitialCallWithProvider(): void
    {
        $provider = fn($context) => "Result: $context";
        $chain = new Chain($provider);

        $result = $chain->call('test');

        $this->assertEquals('Result: test', $result);
    }

    public function testMiddlewareExecutionOrder(): void
    {
        $executed = [];

        $middleware1 = function ($context, $next) use (&$executed) {
            $executed[] = 'middleware1';

            return $next($context).' + middleware1';
        };

        $middleware2 = function ($context, $next) use (&$executed) {
            $executed[] = 'middleware2';

            return $next($context).' + middleware2';
        };

        $chain = new Chain(fn($c) => "base:$c", [$middleware1, $middleware2]);

        $result = $chain->call('test');

        $this->assertEquals(['middleware1', 'middleware2'], $executed);
        $this->assertEquals('base:test + middleware2 + middleware1', $result);
    }

    public function testWithProviderCreatesNewChain(): void
    {
        $chain = new Chain(fn($c) => "old provider:$c");

        $newChain = $chain->withProvider(fn($c) => "new provider:$c");

        $this->assertNotSame($chain, $newChain);
        $this->assertEquals('new provider:test', $newChain->call('test'));
    }

    public function testMiddlewareCanModifyContext(): void
    {
        $middleware = function (&$context, $next) {
            $context .= '-modified';

            return $next($context);
        };

        $chain = new Chain(fn($c) => "final:$c", [$middleware]);

        $result = $chain->call('input');

        $this->assertEquals('final:input-modified', $result);
    }

    public function testMemoizationCachesResults(): void
    {
        $calls = 0;
        $middleware = function ($context, $next) use (&$calls) {
            $calls++;

            return $next($context);
        };

        $chain = new Chain(fn($c) => "output:$c")
            ->link($middleware)
            ->memoize();

        $result1 = $chain->call('memo-test');
        $result2 = $chain->call('memo-test');

        $this->assertEquals($result1, $result2);
        $this->assertEquals(1, $calls);
    }

    public function testMemoizationWithDifferentContextDoesNotCache(): void
    {
        $calls = 0;
        $middleware = function ($context, $next) use (&$calls) {
            $calls++;

            return $next($context);
        };

        $chain = new Chain(fn($c) => "output:$c")
            ->link($middleware)
            ->memoize();

        $chain->call('test1');
        $chain->call('test2');

        $this->assertEquals(2, $calls);
    }

    public function testFirstRestartsWithNewInput(): void
    {
        $executed = [];

        $middleware1 = function ($context, $next) use (&$executed) {
            $executed[] = 'middleware1';

            return $next($context);
        };

        $middleware2 = function ($context, $next, $first) use (&$executed) {
            static $recursion;// static variable is used for recursion detection

            $executed[] = 'middleware2';

            if (!$recursion) {
                $recursion = true;

                // Restart the chain with a new input parameter
                return $first('new-input');
            }

            return $next($context);
        };

        $middleware3 = function ($context, $next) use (&$executed) {
            $executed[] = 'middleware3';
            $result = "$context + middleware3";

            return $next($result);
        };

        $chain = new Chain(
            fn($c) => "base:$c",
            [$middleware1, $middleware2, $middleware3],
            1
        );

        $result = $chain->call('initial-input');

        // Assert that the chain restarted with the new input parameter
        $this->assertEquals(
            [
                'middleware1',
                'middleware2',
                'middleware1',
                'middleware2',
                'middleware3',
            ],
            $executed
        );
        $this->assertEquals('base:new-input + middleware3', $result);
    }

    public function testFirstRestartsWithNewInputObject(): void
    {
        $executed = [];

        $middleware1 = function ($context, $next) use (&$executed) {
            $executed[] = 'middleware1';

            return $next($context);
        };

        $middleware2 = function ($context, $next, $first) use (&$executed) {
            static $recursion; // Static variable is used for recursion detection

            $executed[] = 'middleware2';

            if (!$recursion) {
                $recursion = true;

                // Restart the chain with a new input parameter (DateTime object)
                return $first(new \DateTime('now'));
            }

            return $next($context);
        };

        $middleware3 = function (\DateTime $context, $next) use (&$executed) {
            $executed[] = 'middleware3';
            $result = "{$context->format('Y-m-d H:i:s')} + middleware3";

            return $next($result);
        };

        $chain = new Chain(
            fn($c) => "base:$c",
            [$middleware1, $middleware2, $middleware3],
            1
        );

        $initialInput = new \DateTime('now');
        $result = $chain->call($initialInput);

        // Assert that the chain restarted with the new input parameter
        $this->assertEquals(
            [
                'middleware1',
                'middleware2',
                'middleware1',
                'middleware2',
                'middleware3',
            ],
            $executed
        );
        $this->assertStringContainsString('base:', (string)$result);
        $this->assertStringContainsString('+ middleware3', (string)$result);
    }

    public function testMaxRestartsPreventsInfiniteLoops(): void
    {
        $this->expectException(ChainException::class);
        $this->expectExceptionMessage('Too many restarts in middleware chain');

        $middleware = function ($context, $next, $first) {
            return $first($context); // Causes infinite restart loop
        };

        $chain = new Chain(fn($c) => "final:$c", [$middleware], 2);

        $chain->call('loop-test');
    }

    public function testCanLinkAdditionalMiddlewares(): void
    {
        $chain = new Chain(fn($c) => "base:$c");

        $chain = $chain->link(function ($context, $next) {
            return $next($context)." + addedMiddleware";
        });

        $result = $chain->call('test');

        $this->assertEquals('base:test + addedMiddleware', $result);
    }

    public function testMiddlewareCanShortCircuitExecution(): void
    {
        $chain = new Chain(fn($c) => "base:$c");

        $chain = $chain->link(function ($context, $next) {
            return "short-circuited";
        });

        $result = $chain->call('test');

        $this->assertEquals('short-circuited', $result);
    }
}
