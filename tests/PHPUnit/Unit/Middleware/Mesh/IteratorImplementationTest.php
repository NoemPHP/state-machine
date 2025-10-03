<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Mesh;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh implements Iterator interface for foreach loops
 */
#[Group('middleware')]
#[Group('mesh')]
class IteratorImplementationTest extends TestCase
{
    public function testImplementsIteratorInterface(): void
    {
        $mesh = new Mesh();

        $this->assertInstanceOf(\Iterator::class, $mesh);
    }

    public function testForeachLoop(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'a';
        $mesh[] = 'b';
        $mesh[] = 'c';

        $result = [];
        foreach ($mesh as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertSame([0 => 'a', 1 => 'b', 2 => 'c'], $result);
    }

    public function testForeachWithNumericKeys(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'one';
        $mesh[] = 'two';
        $mesh[] = 'three';

        $result = [];
        foreach ($mesh as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertSame([0 => 'one', 1 => 'two', 2 => 'three'], $result);
    }

    public function testMultipleForeachLoops(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'x';
        $mesh[] = 'y';
        $mesh[] = 'z';

        // First iteration
        $firstResult = [];
        foreach ($mesh as $value) {
            $firstResult[] = $value;
        }

        // Second iteration should work the same
        $secondResult = [];
        foreach ($mesh as $value) {
            $secondResult[] = $value;
        }

        $this->assertSame(['x', 'y', 'z'], $firstResult);
        $this->assertSame(['x', 'y', 'z'], $secondResult);
    }

    public function testIteratorWithEmptyMesh(): void
    {
        $mesh = new Mesh();

        $result = [];
        foreach ($mesh as $value) {
            $result[] = $value;
        }

        $this->assertEmpty($result);
    }
}
