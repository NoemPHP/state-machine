<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Iterator;

use Noem\State\Iterator\ParallelDescendingIterator;
use Noem\State\State\HierarchicalState;
use Noem\State\State\ParallelState;
use PHPUnit\Framework\TestCase;

class ParallelDescendantIteratorTest extends TestCase
{

    /**
     * @dataProvider stateTree
     */
    public function testIterator(array $states, array $expectedResults)
    {
        $sut = new ParallelDescendingIterator(new \ArrayIterator($states));
        $result = iterator_to_array($sut);
        $this->assertSame($expectedResults, array_keys($result));
    }

    public function stateTree(): \Generator
    {
        $baz = new HierarchicalState('baz');
        $bar = new HierarchicalState('bar', null, $baz);
        $foo = new HierarchicalState('foo', null, $bar);
        $bar->setParent($foo);
        $baz->setParent($bar);

        yield '#1 Simple Passthrough' => [
            [
                'foo' => $foo,
                'bar' => $bar,
                'baz' => $baz,
            ],
            ['foo', 'bar', 'baz'],
        ];

        $baz = new HierarchicalState('baz');
        $bar1 = new HierarchicalState('bar1');
        $bar2 = new HierarchicalState('bar2');
        $bar = new ParallelState('bar', null, $bar1, $bar2);
        $foo = new HierarchicalState('foo', null, $bar);

        yield '#2 Simple Parallel Tree' => [
            [
                'foo' => $foo,
                'bar' => $bar,
                'baz' => $baz,
            ],
            ['foo', 'bar', 'bar1', 'bar2', 'baz'],
        ];

        $baz = new HierarchicalState('baz');
        $bar1 = new HierarchicalState('bar1');
        $bar2_1_1 = new HierarchicalState('bar2_1_1');
        $bar2_1_2 = new HierarchicalState('bar2_1_2');
        $bar2_1_3 = new HierarchicalState('bar2_1_3');
        $bar2_1 = new ParallelState('bar2_1', null, $bar2_1_1, $bar2_1_2, $bar2_1_3);
        $bar2 = new HierarchicalState('bar2', null, $bar2_1);
        $bar = new ParallelState('bar', null, $bar1, $bar2);
        $foo = new HierarchicalState('foo', null, $bar);

        yield '#3 Nested Parallel Tree' => [
            [
                'foo' => $foo,
                'bar' => $bar,
                'baz' => $baz,
            ],
            ['foo', 'bar', 'bar1', 'bar2', 'bar2_1', 'bar2_1_1', 'bar2_1_2', 'bar2_1_3', 'baz'],
        ];
    }
}