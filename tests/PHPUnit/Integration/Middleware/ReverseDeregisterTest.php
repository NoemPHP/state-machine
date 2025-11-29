<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Middleware;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Multiple middleware can be deregistered in reverse order of registration
 */
#[Group('middleware')]
#[Group('chain')]
#[Group('lifecycle-integration')]
class ReverseDeregisterTest extends TestCase
{
    public function testDeregisterInReverseOrderLIFO(): void
    {
        $executions = [];
        $chain = new Chain(fn($c) => $c);

        $deregister1 = $chain->link(function ($c, $next) use (&$executions) {
            $executions[] = 'm1';
            return $next($c);
        });

        $deregister2 = $chain->link(function ($c, $next) use (&$executions) {
            $executions[] = 'm2';
            return $next($c);
        });

        $deregister3 = $chain->link(function ($c, $next) use (&$executions) {
            $executions[] = 'm3';
            return $next($c);
        });

        // All execute (reverse order: m3, m2, m1)
        $chain->call('test');
        $this->assertEquals(['m3', 'm2', 'm1'], $executions);

        // Deregister in reverse order (LIFO)
        $executions = [];
        $deregister3();
        $chain->call('test');
        $this->assertEquals(['m2', 'm1'], $executions);

        $executions = [];
        $deregister2();
        $chain->call('test');
        $this->assertEquals(['m1'], $executions);

        $executions = [];
        $deregister1();
        $chain->call('test');
        $this->assertEmpty($executions);
    }

    public function testDeregisterStackLikeCleanup(): void
    {
        $stack = [];
        $chain = new Chain(fn($c) => $c);

        // Build a stack of middleware
        $deregisters = [];
        for ($i = 1; $i <= 5; $i++) {
            $deregisters[$i] = $chain->link(function ($c, $next) use (&$stack, $i) {
                $stack[] = "enter-$i";
                $result = $next($c);
                $stack[] = "exit-$i";
                return $result;
            });
        }

        // Execute with full stack
        $chain->call('test');
        $this->assertEquals([
            'enter-5', 'enter-4', 'enter-3', 'enter-2', 'enter-1',
            'exit-1', 'exit-2', 'exit-3', 'exit-4', 'exit-5'
        ], $stack);

        // Pop from stack (LIFO)
        $stack = [];
        $deregisters[5]();
        $chain->call('test');
        $this->assertEquals([
            'enter-4', 'enter-3', 'enter-2', 'enter-1',
            'exit-1', 'exit-2', 'exit-3', 'exit-4'
        ], $stack);

        $stack = [];
        $deregisters[4]();
        $chain->call('test');
        $this->assertEquals([
            'enter-3', 'enter-2', 'enter-1',
            'exit-1', 'exit-2', 'exit-3'
        ], $stack);

        $stack = [];
        $deregisters[3]();
        $chain->call('test');
        $this->assertEquals([
            'enter-2', 'enter-1',
            'exit-1', 'exit-2'
        ], $stack);
    }

    public function testReverseDeregisterWithPrepend(): void
    {
        $executions = [];
        $chain = new Chain(fn($c) => $c);

        // Build stack with mix of append and prepend
        $deregister1 = $chain->link(function ($c, $next) use (&$executions) {
            $executions[] = 'appended1';
            return $next($c);
        });

        $deregister2 = $chain->link(function ($c, $next) use (&$executions) {
            $executions[] = 'prepended1';
            return $next($c);
        }, true);

        $deregister3 = $chain->link(function ($c, $next) use (&$executions) {
            $executions[] = 'appended2';
            return $next($c);
        });

        $deregister4 = $chain->link(function ($c, $next) use (&$executions) {
            $executions[] = 'prepended2';
            return $next($c);
        }, true);

        // Order: prepended2, prepended1, appended2, appended1
        $chain->call('test');
        $this->assertEquals(['prepended2', 'prepended1', 'appended2', 'appended1'], $executions);

        // Deregister in reverse order of registration
        $executions = [];
        $deregister4();
        $chain->call('test');
        $this->assertEquals(['prepended1', 'appended2', 'appended1'], $executions);

        $executions = [];
        $deregister3();
        $chain->call('test');
        $this->assertEquals(['prepended1', 'appended1'], $executions);

        $executions = [];
        $deregister2();
        $chain->call('test');
        $this->assertEquals(['appended1'], $executions);

        $executions = [];
        $deregister1();
        $chain->call('test');
        $this->assertEmpty($executions);
    }

    public function testReverseDeregisterDoesNotAffectExecution(): void
    {
        $values = [];
        $chain = new Chain(fn($c) => implode('', $c));

        $deregister1 = $chain->link(function ($c, $next) use (&$values) {
            $values[] = 'start-1';
            array_unshift($c, 'A');
            return $next($c);
        });

        $deregister2 = $chain->link(function ($c, $next) use (&$values) {
            $values[] = 'start-2';
            array_unshift($c, 'B');
            return $next($c);
        });

        $deregister3 = $chain->link(function ($c, $next) use (&$values) {
            $values[] = 'start-3';
            array_unshift($c, 'C');
            return $next($c);
        });

        // Full execution
        $result = $chain->call([]);
        $this->assertEquals('ABC', $result);
        $this->assertEquals(['start-3', 'start-2', 'start-1'], $values);

        // Deregister in reverse
        $values = [];
        $deregister3();
        $result = $chain->call([]);
        $this->assertEquals('AB', $result);

        $values = [];
        $deregister2();
        $result = $chain->call([]);
        $this->assertEquals('A', $result);

        $values = [];
        $deregister1();
        $result = $chain->call([]);
        $this->assertEquals('', $result);
    }

    public function testReverseDeregisterWithMemoization(): void
    {
        $executions = 0;
        $chain = (new Chain(fn($c) => $c))->memoize();

        $deregister1 = $chain->link(function ($c, $next) use (&$executions) {
            $executions++;
            return $next($c);
        });

        $deregister2 = $chain->link(function ($c, $next) use (&$executions) {
            $executions++;
            return $next($c);
        });

        // First call - both execute and memoize
        $chain->call('test');
        $this->assertEquals(2, $executions);

        // Second call - memoized
        $chain->call('test');
        $this->assertEquals(2, $executions);

        // Deregister in reverse - invalidates cache but memoization still active
        $deregister2();
        $chain->call('test');
        $this->assertEquals(2, $executions); // Memoization still cached, no execution

        $deregister1();
        $chain->call('test');
        $this->assertEquals(2, $executions); // Still memoized
    }

    public function testComplexReverseDeregisterScenario(): void
    {
        $log = [];
        $chain = new Chain(fn($c) => $c);

        // Middleware that logs entry and exit
        $createMiddleware = function (string $name) use (&$log) {
            return function ($c, $next) use ($name, &$log) {
                $log[] = "enter-$name";
                $result = $next($c);
                $log[] = "exit-$name";
                return $result;
            };
        };

        // Register 10 middleware
        $deregisters = [];
        foreach (range('A', 'J') as $letter) {
            $deregisters[$letter] = $chain->link($createMiddleware($letter));
        }

        // Full execution
        $chain->call('test');
        $expectedEnter = ['enter-J', 'enter-I', 'enter-H', 'enter-G', 'enter-F', 
                          'enter-E', 'enter-D', 'enter-C', 'enter-B', 'enter-A'];
        $expectedExit = ['exit-A', 'exit-B', 'exit-C', 'exit-D', 'exit-E',
                         'exit-F', 'exit-G', 'exit-H', 'exit-I', 'exit-J'];
        $this->assertEquals(array_merge($expectedEnter, $expectedExit), $log);

        // Deregister in reverse order (J -> I -> H ...)
        foreach (array_reverse(range('A', 'J')) as $letter) {
            $log = [];
            $deregisters[$letter]();
            
            // Each deregister should remove one layer
            if ($letter > 'A') {
                $chain->call('test');
                $this->assertNotContains("enter-$letter", $log, "$letter should not execute after deregister");
            }
        }

        // Final call with all deregistered
        $log = [];
        $chain->call('test');
        $this->assertEmpty($log, 'No middleware should execute when all deregistered');
    }
}
