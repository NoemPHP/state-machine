<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\TypeSafety;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chain is generic over Context (C) and Return (R) types
 */
#[Group('middleware')]
#[Group('type-safety')]
class ChainGenericsTest extends TestCase
{
    public function testChainHandlesStringContext(): void
    {
        $chain = new Chain(fn(string $c): string => strtoupper($c));

        $result = $chain->call('hello');

        $this->assertIsString($result);
        $this->assertEquals('HELLO', $result);
    }

    public function testChainHandlesIntContext(): void
    {
        $chain = new Chain(fn(int $c): int => $c * 2);

        $result = $chain->call(5);

        $this->assertIsInt($result);
        $this->assertEquals(10, $result);
    }

    public function testChainHandlesObjectContext(): void
    {
        $chain = new Chain(fn(\stdClass $c): \stdClass => $c);

        $obj = new \stdClass();
        $obj->value = 'test';

        $result = $chain->call($obj);

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertEquals('test', $result->value);
    }

    public function testChainHandlesArrayContext(): void
    {
        $chain = new Chain(fn(array $c): array => array_map(fn($x) => $x * 2, $c));

        $result = $chain->call([1, 2, 3]);

        $this->assertIsArray($result);
        $this->assertEquals([2, 4, 6], $result);
    }

    public function testChainHandlesDifferentReturnType(): void
    {
        $chain = new Chain(fn(string $c): int => strlen($c));

        $result = $chain->call('hello');

        $this->assertIsInt($result);
        $this->assertEquals(5, $result);
    }
}
