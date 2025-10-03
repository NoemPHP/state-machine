<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Chain;

use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Memoization uses strict equality (===) by default but accepts custom equality checks
 */
#[Group('middleware')]
#[Group('chain')]
class MemoizationEqualityTest extends TestCase
{
    public function testMemoizationWithDifferentContextDoesNotCache(): void
    {
        $calls = 0;

        $chain = new Chain(
            function ($c) use (&$calls) {
                $calls++;
                return "result";
            }
        );
        $chain = $chain->memoize();

        $obj1 = new \stdClass();
        $obj2 = new \stdClass();

        $chain->call($obj1);
        $this->assertEquals(1, $calls);

        $chain->call($obj2);
        $this->assertEquals(2, $calls, 'Different objects should not share cache by default (strict equality)');
    }

    public function testMemoizationUsesStrictEqualityByDefault(): void
    {
        $calls = 0;
        $chain = new Chain(function ($c) use (&$calls) {
            $calls++;
            return $c;
        });
        $chain = $chain->memoize();

        $chain->call(1);
        $this->assertEquals(1, $calls);

        $chain->call('1');
        $this->assertEquals(2, $calls, 'String "1" and int 1 should not match with strict equality');
    }

    public function testMemoizationWithCustomEquality(): void
    {
        $calls = 0;
        $customEquality = fn($a, $b) => $a == $b; // Loose equality

        $chain = new Chain(function ($c) use (&$calls) {
            $calls++;
            return $c;
        });
        $chain = $chain->memoize($customEquality);

        $chain->call(1);
        $this->assertEquals(1, $calls);

        $chain->call('1');
        $this->assertEquals(1, $calls, 'With custom loose equality, "1" and 1 should match');
    }

    public function testMemoizationWithObjectEquality(): void
    {
        $calls = 0;
        // Custom equality that compares object properties
        $customEquality = function ($a, $b) {
            if (is_object($a) && is_object($b)) {
                return $a->id ?? null === $b->id ?? null;
            }
            return $a === $b;
        };

        $chain = new Chain(function ($c) use (&$calls) {
            $calls++;
            return 'result';
        });
        $chain = $chain->memoize($customEquality);

        $obj1 = new \stdClass();
        $obj1->id = 123;

        $obj2 = new \stdClass();
        $obj2->id = 123;

        $chain->call($obj1);
        $this->assertEquals(1, $calls);

        $chain->call($obj2);
        $this->assertEquals(1, $calls, 'Objects with same id should match with custom equality');
    }
}
