<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Iterator;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\HierarchicalStateInterface;
use Noem\State\Iterator\AscendingStateIterator;
use Noem\State\State\HierarchicalState;

class AscendingStateIteratorTest extends MockeryTestCase
{

    /**
     * @dataProvider stateTree
     */
    public function testIterator(HierarchicalStateInterface $state, HierarchicalStateInterface ...$expectedResults)
    {
        $sut = new AscendingStateIterator($state);
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
            $baz,
            $baz,
            $bar,
            $foo,
        ];
    }
}