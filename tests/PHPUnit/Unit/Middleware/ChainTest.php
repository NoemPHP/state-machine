<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware;

use Noem\State\Middleware\Chain;
use Noem\State\Middleware\ChainException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('middleware')]
class ChainTest extends TestCase
{
    /**
     * Acceptance Criterion: A portable chain object can be created with a basic provider
     */
    public function testPortableChainCreationWithProvider(): void
    {
        $provider = fn($context) => "Result: $context";
        $chain = new Chain($provider);

        $result = $chain->call('test');

        $this->assertEquals('Result: test', $result);
    }

    /**
     * Acceptance Criterion: Middleware executes in reverse order (last linked executes first)
     * Acceptance Criterion: Multiple middleware can be passed to constructor as an array
     */
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

    /**
     * Acceptance Criterion: A chain can be created without a provider and one can be added later using withProvider()
     * Acceptance Criterion: withProvider() creates a new Chain instance without modifying the original
     */
    public function testWithProviderCreatesNewChain(): void
    {
        $chain = new Chain(fn($c) => "old provider:$c");

        $newChain = $chain->withProvider(fn($c) => "new provider:$c");

        $this->assertNotSame($chain, $newChain);
        $this->assertEquals('new provider:test', $newChain->call('test'));
    }

    /**
     * Acceptance Criterion: Middleware can modify the context passed through the chain
     */
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

    /**
     * Acceptance Criterion: Chain supports memoization to cache results for identical inputs
     * Acceptance Criterion: Memoization prevents repeated calculations
     */
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

    /**
     * Acceptance Criterion: Memoization uses strict equality (===) by default but accepts custom equality checks
     */
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

    /**
     * Acceptance Criterion: Chain provides a 'first' parameter to middleware allowing restart with new input
     */
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

    /**
     * Acceptance Criterion: Chain properly handles both scalar and object contexts
     */
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

    /**
     * Acceptance Criterion: Chain prevents infinite loops through configurable restart limits (default: 1)
     * Acceptance Criterion: ChainException is thrown when restart limits are exceeded
     * Acceptance Criterion: Chain restart tracking prevents infinite recursion
     * Acceptance Criterion: Chain supports configurable restart limits to prevent performance issues
     */
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

    /**
     * Acceptance Criterion: A chain supports linking multiple middleware functions in sequence
     */
    public function testCanLinkAdditionalMiddlewares(): void
    {
        $chain = new Chain(fn($c) => "base:$c");

        $chain = $chain->link(function ($context, $next) {
            return $next($context)." + addedMiddleware";
        });

        $result = $chain->call('test');

        $this->assertEquals('base:test + addedMiddleware', $result);
    }

    /**
     * Acceptance Criterion: Middleware can short-circuit execution by not calling next()
     */
    public function testMiddlewareCanShortCircuitExecution(): void
    {
        $chain = new Chain(fn($c) => "base:$c");

        $chain = $chain->link(function ($context, $next) {
            return "short-circuited";
        });

        $result = $chain->call('test');

        $this->assertEquals('short-circuited', $result);
    }

    /**
     * Acceptance Criterion: A chain can be created without a provider and one can be added later using withProvider()
     */
    public function testChainCreationWithoutProvider(): void
    {
        $chain = new Chain();
        $chainWithProvider = $chain->withProvider(fn($c) => "Result: $c");
        
        $result = $chainWithProvider->call('test');
        
        $this->assertEquals('Result: test', $result);
    }

    /**
     * Acceptance Criterion: withProvider() creates a new Chain instance without modifying the original
     */
    public function testWithProviderImmutability(): void
    {
        $chain = new Chain(fn($c) => "old provider:$c");
        $newChain = $chain->withProvider(fn($c) => "new provider:$c");

        $this->assertNotSame($chain, $newChain);
        $this->assertEquals('old provider:test', $chain->call('test'));
        $this->assertEquals('new provider:test', $newChain->call('test'));
    }

    /**
     * Acceptance Criterion: Chain properly handles both scalar and object contexts
     */
    public function testScalarAndObjectContexts(): void
    {
        // Test with scalar context
        $scalarChain = new Chain(fn($c) => "scalar:$c");
        $this->assertEquals('scalar:test', $scalarChain->call('test'));
        
        // Test with object context
        $objectChain = new Chain(fn(\DateTime $c) => "date:" . $c->format('Y-m-d'));
        $date = new \DateTime('2024-01-01');
        $this->assertEquals('date:2024-01-01', $objectChain->call($date));
    }
}
