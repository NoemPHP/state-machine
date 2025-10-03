<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Mesh;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh provides getArrayCopy() method for array conversion
 */
#[Group('middleware')]
#[Group('mesh')]
class ArrayCopyTest extends TestCase
{
    public function testGetArrayCopyReturnsArray(): void
    {
        $mesh = new Mesh();
        $mesh['key'] = 'value';
        
        $result = $mesh->getArrayCopy();
        
        $this->assertIsArray($result);
    }

    public function testGetArrayCopyContainsData(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'one';
        $mesh[] = 'two';
        $mesh[] = 'three';
        
        $result = $mesh->getArrayCopy();
        
        $this->assertSame(['one', 'two', 'three'], $result);
    }

    public function testGetArrayCopyWithNumericKeys(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'a';
        $mesh[] = 'b';
        $mesh[] = 'c';
        
        $result = $mesh->getArrayCopy();
        
        $this->assertSame(['a', 'b', 'c'], $result);
    }

    public function testGetArrayCopyReturnsEmptyArrayForEmptyMesh(): void
    {
        $mesh = new Mesh();
        
        $result = $mesh->getArrayCopy();
        
        $this->assertSame([], $result);
    }

    public function testGetArrayCopyIsIndependent(): void
    {
        $mesh = new Mesh();
        $mesh['key'] = 'original';
        
        $copy = $mesh->getArrayCopy();
        $copy['key'] = 'modified';
        
        // Modifying the copy should not affect the mesh
        $this->assertSame('original', $mesh['key']);
    }

    public function testGetArrayCopyWithMixedTypes(): void
    {
        $mesh = new Mesh();
        $mesh[] = 'text';
        $mesh[] = 42;
        $mesh[] = ['nested' => 'data'];
        $mesh[] = (object)['prop' => 'value'];
        
        $result = $mesh->getArrayCopy();
        
        $this->assertSame('text', $result[0]);
        $this->assertSame(42, $result[1]);
        $this->assertSame(['nested' => 'data'], $result[2]);
        $this->assertEquals((object)['prop' => 'value'], $result[3]);
    }

    public function testGetArrayCopyMultipleTimes(): void
    {
        $mesh = new Mesh();
        $mesh['key'] = 'value';
        
        $copy1 = $mesh->getArrayCopy();
        $copy2 = $mesh->getArrayCopy();
        
        $this->assertEquals($copy1, $copy2, 'Arrays should have same content');
    }
}
