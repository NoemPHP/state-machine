<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Middleware can modify the context passed through the chain
 */
#[Group('middleware')]
#[Group('chain')]
class MiddlewareModifiesContextTest extends TestCase
{
    public function testMiddlewareCanModifyContext(): void
    {
        $middleware = function ($context, $next) {
            $context .= '-modified';
            return $next($context);
        };

        $chain = new Chain(fn($c) => "final:$c", [$middleware]);

        $result = $chain->call('input');

        $this->assertEquals('final:input-modified', $result);
    }

    public function testMultipleMiddlewareCanModifyContext(): void
    {
        $middleware1 = function ($c, $next) {
            $modified = $c . '-m1';
            return $next($modified);
        };
        $middleware2 = function ($c, $next) {
            $modified = $c . '-m2';
            return $next($modified);
        };
        $middleware3 = function ($c, $next) {
            $modified = $c . '-m3';
            return $next($modified);
        };

        $chain = new Chain(fn($c) => $c, [$middleware1, $middleware2, $middleware3]);

        $result = $chain->call('start');

        $this->assertEquals('start-m1-m2-m3', $result);
    }

    public function testMiddlewareCanModifyObjectContext(): void
    {
        $middleware = function ($context, $next) {
            $context->value = 'modified';
            return $next($context);
        };

        $context = new \stdClass();
        $context->value = 'original';

        $chain = new Chain(fn($c) => $c, [$middleware]);
        $result = $chain->call($context);

        $this->assertEquals('modified', $result->value);
    }
}
