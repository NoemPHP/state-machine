<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Mesh;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh supports array-style assignment without explicit keys (auto-increment)
 */
#[Group('middleware')]
#[Group('mesh')]
class AutoIncrementTest extends TestCase
{
    public function testAutoIncrementWithoutKey(): void
    {
        $mesh = new Mesh();
        
        $mesh[] = 'first';
        $mesh[] = 'second';
        
        $this->assertSame('first', $mesh[0]);
        $this->assertSame('second', $mesh[1]);
    }

    public function testAutoIncrementMultipleValues(): void
    {
        $mesh = new Mesh();
        
        $mesh[] = 'a';
        $mesh[] = 'b';
        $mesh[] = 'c';
        $mesh[] = 'd';
        
        $this->assertSame('a', $mesh[0]);
        $this->assertSame('b', $mesh[1]);
        $this->assertSame('c', $mesh[2]);
        $this->assertSame('d', $mesh[3]);
    }

    public function testAutoIncrementAfterExplicitKeys(): void
    {
        $mesh = new Mesh();
        
        $mesh[0] = 'explicit-zero';
        $mesh[1] = 'explicit-one';
        $mesh[] = 'auto-next';
        
        $this->assertSame('explicit-zero', $mesh[0]);
        $this->assertSame('explicit-one', $mesh[1]);
        $this->assertSame('auto-next', $mesh[2]);
    }

    public function testAutoIncrementWithMixedTypes(): void
    {
        $mesh = new Mesh();
        
        $mesh[] = 'string';
        $mesh[] = 42;
        $mesh[] = ['array'];
        $mesh[] = (object)['prop' => 'value'];
        
        $this->assertSame('string', $mesh[0]);
        $this->assertSame(42, $mesh[1]);
        $this->assertSame(['array'], $mesh[2]);
        $this->assertEquals((object)['prop' => 'value'], $mesh[3]);
    }

    public function testAutoIncrementBehavesLikeArray(): void
    {
        $mesh = new Mesh();
        $array = [];
        
        $mesh[] = 'test1';
        $array[] = 'test1';
        
        $mesh[] = 'test2';
        $array[] = 'test2';
        
        $this->assertSame($array[0], $mesh[0]);
        $this->assertSame($array[1], $mesh[1]);
    }
}
