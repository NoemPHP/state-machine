<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A portable chain object can be created with a basic provider
 */
#[Group('middleware')]
#[Group('chain')]
class PortableChainCreationTest extends TestCase
{
    public function testPortableChainCreationWithProvider(): void
    {
        $provider = fn($context) => "Result: $context";
        $chain = new Chain($provider);

        $result = $chain->call('test');

        $this->assertEquals('Result: test', $result);
    }

    public function testProviderReceivesContext(): void
    {
        $provider = function ($context) {
            $this->assertIsString($context);
            return "Processed: $context";
        };

        $chain = new Chain($provider);
        $result = $chain->call('input');

        $this->assertEquals('Processed: input', $result);
    }

    public function testChainIsCallable(): void
    {
        $chain = new Chain(fn($c) => strtoupper($c));

        $this->assertTrue(method_exists($chain, 'call'));
        $this->assertEquals('HELLO', $chain->call('hello'));
    }
}
