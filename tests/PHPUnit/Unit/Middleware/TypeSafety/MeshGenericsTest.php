<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\TypeSafety;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh supports generic Key (TKey) and Value (TValue) types
 */
#[Group('middleware')]
#[Group('type-safety')]
class MeshGenericsTest extends TestCase
{
    public function testMeshHandlesStringKeys(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $mesh = new Mesh($data);

        $this->assertEquals('value1', $mesh['key1']);
        $this->assertEquals('value2', $mesh['key2']);
    }

    public function testMeshHandlesIntKeys(): void
    {
        $data = [0 => 'first', 1 => 'second', 2 => 'third'];
        $mesh = new Mesh($data);

        $this->assertEquals('first', $mesh[0]);
        $this->assertEquals('second', $mesh[1]);
        $this->assertEquals('third', $mesh[2]);
    }

    public function testMeshHandlesStringValues(): void
    {
        $mesh = new Mesh();
        $mesh['key'] = 'string value';

        $this->assertIsString($mesh['key']);
        $this->assertEquals('string value', $mesh['key']);
    }

    public function testMeshHandlesIntValues(): void
    {
        $mesh = new Mesh();
        $mesh['number'] = 42;

        $this->assertIsInt($mesh['number']);
        $this->assertEquals(42, $mesh['number']);
    }

    public function testMeshHandlesObjectValues(): void
    {
        $mesh = new Mesh();
        $obj = new \stdClass();
        $obj->property = 'value';

        $mesh['object'] = $obj;

        $this->assertInstanceOf(\stdClass::class, $mesh['object']);
        $this->assertEquals('value', $mesh['object']->property);
    }

    public function testMeshHandlesArrayValues(): void
    {
        $mesh = new Mesh();
        $mesh['array'] = [1, 2, 3];

        $this->assertIsArray($mesh['array']);
        $this->assertEquals([1, 2, 3], $mesh['array']);
    }

    public function testMeshHandlesMixedTypes(): void
    {
        $data = [
            'string' => 'text',
            'int' => 123,
            'array' => [1, 2, 3],
            'object' => (object)['key' => 'value']
        ];
        $mesh = new Mesh($data);

        $this->assertIsString($mesh['string']);
        $this->assertIsInt($mesh['int']);
        $this->assertIsArray($mesh['array']);
        $this->assertIsObject($mesh['object']);
    }
}
