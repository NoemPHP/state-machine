<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Mesh;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh properly handles iterator methods current(), next(), key(), valid(), rewind()
 */
#[Group('middleware')]
#[Group('mesh')]
class IteratorMethodsTest extends TestCase
{
    public function testCurrent(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'first';
        $mesh[] = 'second';

        $this->assertSame('first', $mesh->current());
    }

    public function testNext(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'first';
        $mesh[] = 'second';

        $this->assertSame('first', $mesh->current());

        $mesh->next();
        $this->assertSame('second', $mesh->current());
    }

    public function testKey(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'first';
        $mesh[] = 'second';

        $this->assertSame(0, $mesh->key());

        $mesh->next();
        $this->assertSame(1, $mesh->key());
    }

    public function testValid(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'one';
        $mesh[] = 'two';

        $this->assertTrue($mesh->valid());

        $mesh->next();
        $this->assertTrue($mesh->valid());

        $mesh->next();
        $this->assertFalse($mesh->valid());
    }

    public function testRewind(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'x';
        $mesh[] = 'y';

        $mesh->next();
        $this->assertSame(1, $mesh->key());

        $mesh->rewind();
        $this->assertSame(0, $mesh->key());
    }

    public function testCompleteIterationCycle(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'a';
        $mesh[] = 'b';
        $mesh[] = 'c';

        // Initial state
        $this->assertTrue($mesh->valid());
        $this->assertSame(0, $mesh->key());
        $this->assertSame('a', $mesh->current());

        // First next
        $mesh->next();
        $this->assertTrue($mesh->valid());
        $this->assertSame(1, $mesh->key());
        $this->assertSame('b', $mesh->current());

        // Second next
        $mesh->next();
        $this->assertTrue($mesh->valid());
        $this->assertSame(2, $mesh->key());
        $this->assertSame('c', $mesh->current());

        // Third next (past end)
        $mesh->next();
        $this->assertFalse($mesh->valid());

        // Rewind
        $mesh->rewind();
        $this->assertTrue($mesh->valid());
        $this->assertSame(0, $mesh->key());
        $this->assertSame('a', $mesh->current());
    }

    public function testValidOnEmptyMesh(): void
    {
        $mesh = new Mesh();

        $this->assertFalse($mesh->valid());
    }

    public function testRewindToBeginning(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'first';
        $mesh[] = 'second';
        $mesh[] = 'third';

        // Navigate to end
        $mesh->next();
        $mesh->next();
        $mesh->next();

        $this->assertFalse($mesh->valid());

        // Rewind brings us back to start
        $mesh->rewind();

        $this->assertTrue($mesh->valid());
        $this->assertSame(0, $mesh->key());
        $this->assertSame('first', $mesh->current());
    }

    public function testIteratorMethodsWithNumericPosition(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'one';
        $mesh[] = 'two';

        // Iterator uses numeric positions
        $this->assertTrue($mesh->valid());
        $this->assertSame(0, $mesh->key());

        $mesh->next();
        $this->assertSame(1, $mesh->key());
    }

    public function testManualIterationMatchesForeach(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'a';
        $mesh[] = 'b';
        $mesh[] = 'c';

        // Manual iteration
        $manualResult = [];
        $mesh->rewind();
        while ($mesh->valid()) {
            $manualResult[] = $mesh->current();
            $mesh->next();
        }

        // Foreach iteration
        $foreachResult = [];
        foreach ($mesh as $value) {
            $foreachResult[] = $value;
        }

        $this->assertSame($foreachResult, $manualResult);
    }
}
