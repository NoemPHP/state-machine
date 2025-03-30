<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Middleware;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\TestCase;

class DataTest extends TestCase
{
    private Mesh $data;

    protected function setUp(): void
    {
        $this->data = new Mesh();
    }

    public function testOffsetSetAndGet(): void
    {
        $this->data['foo'] = 'bar';
        $this->assertSame('bar', $this->data['foo']);
    }

    public function testOffsetExists(): void
    {
        $this->data['key'] = 'value';
        $this->assertTrue(isset($this->data['key']));
        $this->assertFalse(isset($this->data['non_existent']));
    }

    public function testOffsetUnset(): void
    {
        $this->data['key'] = 'value';
        unset($this->data['key']);
        $this->assertFalse(isset($this->data['key']));
    }

    public function testOffsetSetWithoutKey(): void
    {
        $this->data[] = 'first';
        $this->data[] = 'second';

        $this->assertSame('first', $this->data[0]);
        $this->assertSame('second', $this->data[1]);
    }

    public function testIteratorImplementation(): void
    {
        $this->data[] = 'a';
        $this->data[] = 'b';
        $this->data[] = 'c';

        $result = [];
        foreach ($this->data as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertSame([0 => 'a', 1 => 'b', 2 => 'c'], $result);
    }

    public function testRewind(): void
    {
        $this->data[] = 'x';
        $this->data[] = 'y';

        $this->data->next();
        $this->data->rewind();

        $this->assertSame(0, $this->data->key());
    }

    public function testValid(): void
    {
        $this->data[] = 'one';
        $this->data[] = 'two';

        $this->assertTrue($this->data->valid());

        $this->data->next();
        $this->assertTrue($this->data->valid());

        $this->data->next();
        $this->assertFalse($this->data->valid());
    }

    public function testExtendWithArray(): void
    {
        $extension = ['extendedKey' => 'extendedValue'];
        $this->data->extendWith($extension);

        $this->assertTrue(isset($this->data['extendedKey']));
        $this->assertSame('extendedValue', $this->data['extendedKey']);
    }

    public function testExtendWithArrayAccess(): void
    {
        $arrayAccessMock = $this->createMock(\ArrayObject::class);
        $arrayAccessMock->method('offsetExists')->willReturn(true);
        $arrayAccessMock->method('offsetGet')->willReturn('mockedValue');

        $this->data->extendWith($arrayAccessMock);

        $this->assertTrue(isset($this->data['anyKey']));
        $this->assertSame('mockedValue', $this->data['anyKey']);
    }
}
