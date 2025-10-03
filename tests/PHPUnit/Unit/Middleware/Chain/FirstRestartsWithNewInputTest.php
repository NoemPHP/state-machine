<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chain provides a 'first' parameter to middleware allowing restart with new input
 */
#[Group('middleware')]
#[Group('chain')]
class FirstRestartsWithNewInputTest extends TestCase
{
    public function testFirstRestartsWithNewInput(): void
    {
        $callCount = 0;

        $middleware = function ($context, $next, $first) use (&$callCount) {
            $callCount++;

            if ($context === 'initial' && $callCount === 1) {
                return $first('restarted');
            }

            return $next($context);
        };

        $chain = new Chain(fn($c) => "result:$c", [$middleware], 2);

        $result = $chain->call('initial');

        $this->assertEquals('result:restarted', $result);
        $this->assertEquals(2, $callCount);
    }

    public function testFirstRestartsWithNewInputObject(): void
    {
        $middleware = function ($context, $next, $first) {
            if ($context->attempt === 1) {
                $newContext = clone $context;
                $newContext->attempt = 2;
                return $first($newContext);
            }
            return $next($context);
        };

        $context = new \stdClass();
        $context->attempt = 1;

        $chain = new Chain(fn($c) => $c, [$middleware], 2);

        $result = $chain->call($context);

        $this->assertEquals(2, $result->attempt);
    }

    public function testFirstCanModifyInputBeforeRestart(): void
    {
        $middleware = function ($context, $next, $first) {
            if (!str_contains($context, 'modified')) {
                return $first($context . '-modified');
            }
            return $next($context);
        };

        $chain = new Chain(fn($c) => $c, [$middleware], 2);

        $result = $chain->call('input');

        $this->assertEquals('input-modified', $result);
    }
}
