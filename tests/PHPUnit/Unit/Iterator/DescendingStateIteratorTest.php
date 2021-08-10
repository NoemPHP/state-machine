<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Iterator;

use Noem\State\HierarchicalStateInterface;
use Noem\State\Iterator\DescendingStateIterator;
use Noem\State\State\HierarchicalState;
use PHPUnit\Framework\TestCase;

class DescendingStateIteratorTest extends TestCase
{

    /**
     * @dataProvider stateTree
     */
    public function testIterator(HierarchicalStateInterface $state, HierarchicalStateInterface ...$expectedResults)
    {
        $sut = new DescendingStateIterator($state);
        foreach ($sut as $id => $implicitState) {
            $currentExpectedState = current($expectedResults);
            $this->assertTrue(
                $implicitState->equals($currentExpectedState),
                sprintf(
                    'Assert that state %s === %s',
                    (string) $implicitState,
                    (string) $currentExpectedState,
                )
            );
            next($expectedResults);
        }
    }

    public function stateTree(): \Generator
    {
        $baz = new HierarchicalState('baz');
        $bar = new HierarchicalState('bar', null, $baz);
        $foo = new HierarchicalState('foo', null, $bar);
        $bar->setParent($foo);
        $baz->setParent($bar);
        yield '#1 Simple Hierarchy' => [
            $foo,
            $foo,
            $bar,
            $baz,

        ];

        $baz3 = new HierarchicalState('baz3');
        $baz2 = new HierarchicalState('baz2');
        $baz = new HierarchicalState('baz');
        $bar = new HierarchicalState('bar', null, $baz2, $baz, $baz3); //intentionally out of order
        $foo = new HierarchicalState('foo', null, $bar);
        $bar->setParent($foo);
        $bar->setInitial($baz); // This is the important part
        $baz->setParent($bar);
        $baz2->setParent($bar);
        $baz3->setParent($bar);
        yield '#2 Make the right choice' => [
            $foo,
            $foo,
            $bar,
            $baz,

        ];
    }
}