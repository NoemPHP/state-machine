<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use Noem\State\Middleware\ChainException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chain prevents infinite loops through configurable restart limits (default 1)
 */
#[Group('middleware')]
#[Group('chain')]
class MaxRestartsPreventsInfiniteLoopsTest extends TestCase
{
    public function testMaxRestartsPreventsInfiniteLoops(): void
    {
        $this->expectException(ChainException::class);
        $this->expectExceptionMessage('restart');
        $restartCount = 0;
        $middleware = function ($context, $next, $first) use (&$restartCount) {
            if ($restartCount > 2) {
                $this->fail('Surpassed max retries!');
            }
            $restartCount++;
            // Always restart - would cause infinite loop
            return $first($context);
        };
        $chain = new Chain(fn($c) => $c, [$middleware], 1);

        $chain->call('test');
    }

    public function testDefaultRestartLimitIsOne(): void
    {
        $this->expectException(ChainException::class);

        $restartCount = 0;
        $middleware = function ($context, $next, $first) use (&$restartCount) {
            $restartCount++;
            if ($restartCount <= 2) {
                return $first($context);
            }
            return $next($context);
        };

        $chain = new Chain(fn($c) => $c, [$middleware]);
        // Should throw after 1 restart (2 total executions)

        $chain->call('test');
    }

    public function testCustomRestartLimit(): void
    {
        $restartCount = 0;

        $middleware = function ($context, $next, $first) use (&$restartCount) {
            $restartCount++;
            if ($restartCount <= 3) {
                return $first($context);
            }
            return $next($context);
        };

        $chain = new Chain(fn($c) => $c, [$middleware], 5);

        $result = $chain->call('test');

        $this->assertEquals('test', $result);
        $this->assertEquals(4, $restartCount);
    }
}
