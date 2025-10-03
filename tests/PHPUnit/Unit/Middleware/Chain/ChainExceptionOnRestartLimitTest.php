<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use Noem\State\Middleware\ChainException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainException is thrown when restart limits are exceeded
 */
#[Group('middleware')]
#[Group('chain')]
class ChainExceptionOnRestartLimitTest extends TestCase
{
    public function testChainExceptionThrownOnExceededRestartLimit(): void
    {
        $this->expectException(ChainException::class);

        $middleware = fn($context, $next, $first) => $first($context);

        $chain = new Chain(fn($c) => $c, [$middleware], 1);

        $chain->call('test');
    }

    public function testExceptionMessageContainsRestartInformation(): void
    {
        try {
            $middleware = fn($context, $next, $first) => $first($context);

            $chain = new Chain(fn($c) => $c, [$middleware], 1);

            $chain->call('test');

            $this->fail('Expected ChainException to be thrown');
        } catch (ChainException $e) {
            $this->assertStringContainsString('restart', strtolower($e->getMessage()));
        }
    }

    public function testNoExceptionWithinRestartLimit(): void
    {
        $restarts = 0;
        $middleware = function ($context, $next, $first) use (&$restarts) {
            if ($restarts < 2) {
                $restarts++;
                return $first($context);
            }
            return $next($context);
        };

        $chain = new Chain(fn($c) => $c, [$middleware], 5);

        $result = $chain->call('test');

        $this->assertEquals('test', $result);
        $this->assertEquals(2, $restarts);
    }

    public function testChainExceptionIsStandardException(): void
    {
        $this->assertTrue(
            is_subclass_of(ChainException::class, \Exception::class),
            'ChainException should extend standard Exception'
        );
    }
}
