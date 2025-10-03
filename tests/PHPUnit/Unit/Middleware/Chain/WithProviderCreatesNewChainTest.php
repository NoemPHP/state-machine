<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A chain can be created without a provider and one can be added later using withProvider()
 */
#[Group('middleware')]
#[Group('chain')]
class WithProviderCreatesNewChainTest extends TestCase
{
    public function testWithProviderCreatesNewChain(): void
    {
        $chain = new Chain(fn($c) => "old provider:$c");

        $newChain = $chain->withProvider(fn($c) => "new provider:$c");

        $this->assertEquals('new provider:test', $newChain->call('test'));
    }

    public function testCanAddProviderLater(): void
    {
        // Create chain with initial provider
        $chain = new Chain(fn($c) => "initial:$c");

        // Replace with new provider
        $updatedChain = $chain->withProvider(fn($c) => "updated:$c");

        $this->assertEquals('updated:test', $updatedChain->call('test'));
    }

    public function testWithProviderPreservesMiddleware(): void
    {
        $middleware = fn($c, $next) => $next($c) . ' + middleware';

        $chain = new Chain(fn($c) => "base:$c", [$middleware]);
        $newChain = $chain->withProvider(fn($c) => "new:$c");

        $result = $newChain->call('test');

        $this->assertStringContainsString('new:test', $result);
        $this->assertStringContainsString('+ middleware', $result);
    }
}
