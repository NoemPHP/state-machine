<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\TypeSafety;

use Noem\State\Middleware\Chain;
use Noem\State\Middleware\ChainInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainInterface supports generic type parameters
 */
#[Group('middleware')]
#[Group('type-safety')]
class ChainInterfaceTest extends TestCase
{
    public function testChainImplementsChainInterface(): void
    {
        $chain = new Chain(fn($c) => $c);

        $this->assertInstanceOf(ChainInterface::class, $chain);
    }

    public function testChainInterfaceLink(): void
    {
        $chain = new Chain(fn($c) => $c);

        $this->assertInstanceOf(ChainInterface::class, $chain);

        $newChain = $chain->link(function ($context, $next) {
            return $next($context);
        });

        $this->assertInstanceOf(ChainInterface::class, $newChain);
        $this->assertSame($chain, $newChain); // link returns same instance
    }

    public function testChainInterfaceCall(): void
    {
        $chain = new Chain(fn(string $c): string => strtoupper($c));

        $result = $chain->call('test');

        $this->assertEquals('TEST', $result);
    }

    public function testChainInterfaceWithMiddleware(): void
    {
        $chain = new Chain(fn($c) => $c);
        $chain->link(function ($context, $next) {
            $modified = $context . '-modified';
            return $next($modified);
        });

        $result = $chain->call('input');

        $this->assertEquals('input-modified', $result);
    }
}
