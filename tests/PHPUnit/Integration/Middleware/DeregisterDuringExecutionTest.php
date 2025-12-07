<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Middleware;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Deregistering during chain execution affects subsequent calls but not current execution
 */
#[Group('middleware')]
#[Group('chain')]
#[Group('lifecycle-integration')]
class DeregisterDuringExecutionTest extends TestCase
{
    public function testDeregisterDuringExecutionDoesNotAffectCurrentCall(): void
    {
        $executionOrder = [];
        $chain = new Chain(fn($c) => $c);

        $deregister2 = null;

        $chain->link(function ($c, $next) use (&$executionOrder) {
            $executionOrder[] = 'm1-before';
            $result = $next($c);
            $executionOrder[] = 'm1-after';
            return $result;
        });

        $deregister2 = $chain->link(function ($c, $next) use (&$executionOrder, &$deregister2) {
            $executionOrder[] = 'm2-before';
            $deregister2(); // Deregister self during execution
            $result = $next($c);
            $executionOrder[] = 'm2-after';
            return $result;
        });

        $chain->link(function ($c, $next) use (&$executionOrder) {
            $executionOrder[] = 'm3-before';
            $result = $next($c);
            $executionOrder[] = 'm3-after';
            return $result;
        });

        // First call - m2 deregisters itself but still completes
        $chain->call('test1');
        $this->assertEquals([
            'm3-before', 'm2-before', 'm1-before',
            'm1-after', 'm2-after', 'm3-after'
        ], $executionOrder, 'Current execution should complete normally');

        // Second call - m2 should not execute
        $executionOrder = [];
        $chain->call('test2');
        $this->assertEquals([
            'm3-before', 'm1-before',
            'm1-after', 'm3-after'
        ], $executionOrder, 'Subsequent calls should not include deregistered middleware');
    }

    public function testDeregisterOtherMiddlewareDuringExecution(): void
    {
        $executionOrder = [];
        $chain = new Chain(fn($c) => $c);

        $deregister1 = $chain->link(function ($c, $next) use (&$executionOrder) {
            $executionOrder[] = 'm1';
            return $next($c);
        });

        $chain->link(function ($c, $next) use (&$executionOrder, &$deregister1) {
            $executionOrder[] = 'm2';
            $deregister1(); // Deregister m1 while m2 is executing
            return $next($c);
        });

        $chain->link(function ($c, $next) use (&$executionOrder) {
            $executionOrder[] = 'm3';
            return $next($c);
        });

        // First call - all execute, m1 deregistered during m2
        $chain->call('test1');
        $this->assertEquals(['m3', 'm2', 'm1'], $executionOrder);

        // Second call - m1 should not execute
        $executionOrder = [];
        $chain->call('test2');
        $this->assertEquals(['m3', 'm2'], $executionOrder);
    }

    public function testMultipleDeregistersFromSingleMiddleware(): void
    {
        $executionOrder = [];
        $chain = new Chain(fn($c) => $c);

        $deregister1 = $chain->link(function ($c, $next) use (&$executionOrder) {
            $executionOrder[] = 'm1';
            return $next($c);
        });

        $deregister2 = $chain->link(function ($c, $next) use (&$executionOrder) {
            $executionOrder[] = 'm2';
            return $next($c);
        });

        $deregister3 = $chain->link(function ($c, $next) use (&$executionOrder) {
            $executionOrder[] = 'm3';
            return $next($c);
        });

        // m4 deregisters m1, m2, and m3 during execution
        $chain->link(function ($c, $next) use (&$executionOrder, &$deregister1, &$deregister2, &$deregister3) {
            $executionOrder[] = 'm4-before';
            $deregister1();
            $deregister2();
            $deregister3();
            $result = $next($c);
            $executionOrder[] = 'm4-after';
            return $result;
        });

        // First call - all execute
        $chain->call('test1');
        $this->assertEquals(['m4-before', 'm3', 'm2', 'm1', 'm4-after'], $executionOrder);

        // Second call - only m4 executes
        $executionOrder = [];
        $chain->call('test2');
        $this->assertEquals(['m4-before', 'm4-after'], $executionOrder);
    }

    public function testDeregisterInNestedMiddleware(): void
    {
        $log = [];
        $chain = new Chain(fn($c) => $c);

        $deregisterInner = null;

        $chain->link(function ($c, $next) use (&$log) {
            $log[] = 'outer-before';
            $result = $next($c);
            $log[] = 'outer-after';
            return $result;
        });

        $deregisterInner = $chain->link(function ($c, $next) use (&$log, &$deregisterInner) {
            $log[] = 'inner-before';
            $result = $next($c);
            $log[] = 'inner-after';
            $deregisterInner(); // Deregister after next() completes
            return $result;
        });

        // First call - inner (last linked) is outermost, wrapping outer
        $chain->call('test1');
        $this->assertEquals(['inner-before', 'outer-before', 'outer-after', 'inner-after'], $log);

        // Second call - inner should not execute
        $log = [];
        $chain->call('test2');
        $this->assertEquals(['outer-before', 'outer-after'], $log);
    }

    public function testDeregisterBeforeCallingNext(): void
    {
        $log = [];
        $chain = new Chain(fn($c) => $c);

        $deregister1 = null;

        $deregister1 = $chain->link(function ($c, $next) use (&$log, &$deregister1) {
            $log[] = 'm1-start';
            $deregister1(); // Deregister before calling next
            $result = $next($c);
            $log[] = 'm1-end';
            return $result;
        });

        $chain->link(function ($c, $next) use (&$log) {
            $log[] = 'm2';
            return $next($c);
        });

        // First call - m1 still completes
        $chain->call('test1');
        $this->assertEquals(['m2', 'm1-start', 'm1-end'], $log);

        // Second call - m1 removed
        $log = [];
        $chain->call('test2');
        $this->assertEquals(['m2'], $log);
    }

    public function testDeregisterInExceptionHandler(): void
    {
        $log = [];
        $chain = new Chain(fn($c) => $c);

        $deregisterErrorHandler = null;

        $chain->link(function ($c, $next) use (&$log) {
            $log[] = 'throwing';
            throw new \RuntimeException('Test exception');
        });

        $deregisterErrorHandler = $chain->link(function ($c, $next) use (&$log, &$deregisterErrorHandler) {
            $log[] = 'error-handler-before';
            try {
                $result = $next($c);
                $log[] = 'no-error';
                return $result;
            } catch (\RuntimeException $e) {
                $log[] = 'caught-error';
                $deregisterErrorHandler(); // Remove handler after catching
                return 'handled';
            }
        });

        // First call - error handled, handler deregisters
        $result = $chain->call('test1');
        $this->assertEquals('handled', $result);
        $this->assertEquals(['error-handler-before', 'throwing', 'caught-error'], $log);

        // Second call - no error handler, exception propagates
        $log = [];
        $this->expectException(\RuntimeException::class);
        $chain->call('test2');
    }

    public function testConcurrentDeregisterAttempts(): void
    {
        $log = [];
        $chain = new Chain(fn($c) => $c);

        $deregister1 = null;

        $deregister1 = $chain->link(function ($c, $next) use (&$log, &$deregister1) {
            $log[] = 'm1-start';
            $deregister1(); // First deregister
            $result = $next($c);
            $deregister1(); // Second deregister (should be safe/idempotent)
            $log[] = 'm1-end';
            return $result;
        });

        // Should handle multiple deregisters gracefully
        $chain->call('test1');
        $this->assertEquals(['m1-start', 'm1-end'], $log);

        // Subsequent call - m1 removed
        $log = [];
        $chain->call('test2');
        $this->assertEmpty($log);
    }

    public function testDeregisterWithContextModification(): void
    {
        $chain = new Chain(fn($c) => $c);
        $deregisterModifier = null;

        $deregisterModifier = $chain->link(function ($c, $next) use (&$deregisterModifier) {
            $deregisterModifier();
            $modified = is_array($c) ? array_merge($c, ['modified' => true]) : ['original' => $c, 'modified' => true];
            return $next($modified);
        });

        // First call - context modified, middleware deregisters
        $result1 = $chain->call('test1');
        $this->assertEquals(['original' => 'test1', 'modified' => true], $result1);

        // Second call - context not modified
        $result2 = $chain->call('test2');
        $this->assertEquals('test2', $result2);
    }

    public function testDeregisterPropagationThroughChain(): void
    {
        $executionLog = [];
        $chain = new Chain(fn($c) => $c);

        // Stack: m1 -> m2 -> m3 -> m4
        // m2 will deregister m1, m3, and itself during execution

        $deregister1 = $chain->link(function ($c, $next) use (&$executionLog) {
            $executionLog[] = 'm1';
            return $next($c);
        });

        $deregister3 = null;

        $deregister2 = $chain->link(function ($c, $next) use (&$executionLog, &$deregister1, &$deregister3) {
            $executionLog[] = 'm2-start';
            $deregister1(); // Remove m1
            $result = $next($c);
            $deregister3(); // Remove m3
            $executionLog[] = 'm2-end';
            return $result;
        });

        $deregister3 = $chain->link(function ($c, $next) use (&$executionLog) {
            $executionLog[] = 'm3';
            return $next($c);
        });

        $chain->link(function ($c, $next) use (&$executionLog, &$deregister2) {
            $executionLog[] = 'm4-start';
            $result = $next($c);
            $deregister2(); // Remove m2 after it executes
            $executionLog[] = 'm4-end';
            return $result;
        });

        // First call - all execute, various deregistrations occur
        $chain->call('test1');
        $this->assertEquals(['m4-start', 'm3', 'm2-start', 'm1', 'm2-end', 'm4-end'], $executionLog);

        // Second call - only m4 executes (m1, m2, m3 all deregistered)
        $executionLog = [];
        $chain->call('test2');
        $this->assertEquals(['m4-start', 'm4-end'], $executionLog);
    }
}
