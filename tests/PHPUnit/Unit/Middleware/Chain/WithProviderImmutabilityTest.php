<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: withProvider() creates a new Chain instance without modifying the original
 */
#[Group('middleware')]
#[Group('chain')]
class WithProviderImmutabilityTest extends TestCase
{
    public function testWithProviderDoesNotModifyOriginal(): void
    {
        $chain = new Chain(fn($c) => "old provider:$c");

        $newChain = $chain->withProvider(fn($c) => "new provider:$c");

        $this->assertNotSame($chain, $newChain);
        $this->assertEquals('old provider:test', $chain->call('test'));
        $this->assertEquals('new provider:test', $newChain->call('test'));
    }

    public function testOriginalChainUnaffectedByWithProvider(): void
    {
        $original = new Chain(fn($c) => "original:$c");
        $originalResult = $original->call('test');

        $modified = $original->withProvider(fn($c) => "modified:$c");
        $modifiedResult = $modified->call('test');

        // Original should still work the same way
        $this->assertEquals($originalResult, $original->call('test'));
        $this->assertEquals('original:test', $originalResult);
        $this->assertEquals('modified:test', $modifiedResult);
    }

    public function testMultipleWithProviderCallsCreateSeparateInstances(): void
    {
        $base = new Chain(fn($c) => "base:$c");
        $chain1 = $base->withProvider(fn($c) => "chain1:$c");
        $chain2 = $base->withProvider(fn($c) => "chain2:$c");

        $this->assertNotSame($base, $chain1);
        $this->assertNotSame($base, $chain2);
        $this->assertNotSame($chain1, $chain2);

        $this->assertEquals('base:x', $base->call('x'));
        $this->assertEquals('chain1:x', $chain1->call('x'));
        $this->assertEquals('chain2:x', $chain2->call('x'));
    }
}
