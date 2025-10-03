<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('middleware')]
class ChainMiddlewareTest extends TestCase
{
    /**
     * Acceptance Criterion: Middleware can be linked with prepend option to add at the beginning
     */
    public function testPrependMiddleware(): void
    {
        $executed = [];

        $baseChain = new Chain(fn($c) => "base:$c");

        // Add middleware normally (appended to the end)
        $chainWithAppend = $baseChain->link(function ($context, $next) use (&$executed) {
            $executed[] = 'appended';
            return $next($context) . ' + appended';
        });

        // Add middleware with prepend (added to the beginning)
        $chainWithPrepend = $chainWithAppend->link(function ($context, $next) use (&$executed) {
            $executed[] = 'prepended';
            return $next($context) . ' + prepended';
        }, prepend: true);

        $result = $chainWithPrepend->call('test');

        // When prepended, that middleware executes first
        $this->assertEquals(['prepended', 'appended'], $executed);
        $this->assertEquals('base:test + appended + prepended', $result);
    }
}
