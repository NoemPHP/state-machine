<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Middleware;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: One-shot middleware can register, execute once, and deregister itself
 */
#[Group('middleware')]
#[Group('chain')]
#[Group('lifecycle-integration')]
class OneShotMiddlewareTest extends TestCase
{
    public function testOneShotMiddlewarePattern(): void
    {
        $executions = 0;
        $chain = new Chain(fn($c) => $c);

        // Create self-deregistering middleware
        $deregister = $chain->link(function ($c, $next, $first) use (&$executions, &$deregister) {
            $executions++;
            $result = $next($c);
            $deregister(); // Remove self after first execution
            return $result;
        });

        // First call - should execute
        $chain->call('test1');
        $this->assertEquals(1, $executions);

        // Subsequent calls - should not execute
        $chain->call('test2');
        $chain->call('test3');
        $this->assertEquals(1, $executions, 'One-shot middleware should only execute once');
    }

    public function testInitializationHookPattern(): void
    {
        $initialized = false;
        $callCount = 0;

        $chain = new Chain(fn($c) => $c);

        // Initialization middleware that removes itself after first call
        $deregister = $chain->link(function ($c, $next) use (&$initialized, &$deregister) {
            $initialized = true;
            $deregister();
            return $next($c);
        });

        // Regular middleware that counts calls
        $chain->link(function ($c, $next) use (&$callCount) {
            $callCount++;
            return $next($c);
        });

        // First call - initialization happens
        $this->assertFalse($initialized);
        $chain->call('test1');
        $this->assertTrue($initialized);
        $this->assertEquals(1, $callCount);

        // Subsequent calls - no reinitialization
        $chain->call('test2');
        $chain->call('test3');
        $this->assertEquals(3, $callCount, 'Regular middleware should continue executing');
    }

    public function testMultipleOneShotMiddleware(): void
    {
        $executions = ['m1' => 0, 'm2' => 0, 'm3' => 0];
        $chain = new Chain(fn($c) => $c);

        // Three one-shot middleware
        $deregister1 = $chain->link(function ($c, $next) use (&$executions, &$deregister1) {
            $executions['m1']++;
            $deregister1();
            return $next($c);
        });

        $deregister2 = $chain->link(function ($c, $next) use (&$executions, &$deregister2) {
            $executions['m2']++;
            $deregister2();
            return $next($c);
        });

        $deregister3 = $chain->link(function ($c, $next) use (&$executions, &$deregister3) {
            $executions['m3']++;
            $deregister3();
            return $next($c);
        });

        // First call - all execute and deregister
        $chain->call('test1');
        $this->assertEquals(['m1' => 1, 'm2' => 1, 'm3' => 1], $executions);

        // Second call - none execute
        $chain->call('test2');
        $this->assertEquals(['m1' => 1, 'm2' => 1, 'm3' => 1], $executions);
    }

    public function testOneShotMiddlewareWithCondition(): void
    {
        $executions = 0;
        $conditionMet = false;
        $chain = new Chain(fn($c) => $c);

        // One-shot middleware that only deregisters when condition is met
        $deregister = $chain->link(function ($c, $next) use (&$executions, &$conditionMet, &$deregister) {
            $executions++;
            $result = $next($c);

            if ($conditionMet) {
                $deregister();
            }

            return $result;
        });

        // First two calls - condition not met
        $chain->call('test1');
        $chain->call('test2');
        $this->assertEquals(2, $executions);

        // Third call - condition met, middleware deregisters
        $conditionMet = true;
        $chain->call('test3');
        $this->assertEquals(3, $executions);

        // Fourth call - middleware removed
        $chain->call('test4');
        $this->assertEquals(3, $executions, 'Should not execute after condition met');
    }

    public function testOneShotMiddlewareWithPermanentMiddleware(): void
    {
        $oneShotExecutions = 0;
        $permanentExecutions = 0;
        $chain = new Chain(fn($c) => $c);

        // Permanent middleware
        $chain->link(function ($c, $next) use (&$permanentExecutions) {
            $permanentExecutions++;
            return $next($c);
        });

        // One-shot middleware
        $deregister = $chain->link(function ($c, $next) use (&$oneShotExecutions, &$deregister) {
            $oneShotExecutions++;
            $deregister();
            return $next($c);
        });

        // First call
        $chain->call('test1');
        $this->assertEquals(1, $oneShotExecutions);
        $this->assertEquals(1, $permanentExecutions);

        // Subsequent calls
        $chain->call('test2');
        $chain->call('test3');
        $this->assertEquals(1, $oneShotExecutions, 'One-shot should only execute once');
        $this->assertEquals(3, $permanentExecutions, 'Permanent should continue executing');
    }

    public function testOneShotWithContextModification(): void
    {
        $chain = new Chain(fn($c) => $c);
        $contextModified = false;

        // One-shot middleware that modifies context on first call only
        $deregister = $chain->link(function ($c, $next) use (&$contextModified, &$deregister) {
            $contextModified = true;
            $deregister();
            return $next(['initialized' => true, 'original' => $c]);
        });

        // First call - context modified
        $result1 = $chain->call('test1');
        $this->assertTrue($contextModified);
        $this->assertEquals(['initialized' => true, 'original' => 'test1'], $result1);

        // Second call - context not modified
        $result2 = $chain->call('test2');
        $this->assertEquals('test2', $result2, 'Original context should pass through');
    }

    public function testOneShotMiddlewareIdempotency(): void
    {
        $executions = 0;
        $chain = new Chain(fn($c) => $c);

        // One-shot middleware that calls deregister multiple times
        $deregister = $chain->link(function ($c, $next) use (&$executions, &$deregister) {
            $executions++;
            $deregister(); // First deregister
            $deregister(); // Second deregister (should be safe)
            $deregister(); // Third deregister (should be safe)
            return $next($c);
        });

        // Should execute once without error
        $chain->call('test1');
        $this->assertEquals(1, $executions);

        // Should not execute again
        $chain->call('test2');
        $this->assertEquals(1, $executions);
    }
}
