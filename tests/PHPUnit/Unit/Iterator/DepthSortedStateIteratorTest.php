<?php

declare(strict_types=1);

namespace tests\PHPUnit\Unit\Iterator;

use Noem\State\Iterator\DepthSortedStateIterator;
use Noem\State\Loader\Tests\LoaderTestCase;
use Noem\State\State\StateDefinitions;

class DepthSortedStateIteratorTest extends LoaderTestCase
{

    /**
     * @dataProvider stateTree
     * @throws \JsonException
     */
    public function testIterator(string $states, array $expectedResults)
    {
        $map = $this->configureLoader($states)->definitions();
        $closure = \Closure::bind(function (StateDefinitions $class) {
            return $class->tree;
        }, null, StateDefinitions::class);
        $list = $closure($map);
        $sut = new DepthSortedStateIterator(new \ArrayIterator($list));
        $result = iterator_to_array($sut);
        $this->assertSame($expectedResults, array_keys($result), 'Should be able to sort a flat list');
    }

    public function stateTree(): \Generator
    {
        yield '#1 simple hierarchy' => [
            <<<YAML

foo:
    children:
        bar:
            children:
                baz: {}

YAML
            ,
            ['baz', 'bar', 'foo']
        ];

        yield '#1 complex hierarchy' => [
            <<<YAML

foo:
    children:
        bar:
            children:
                bar_2:
                    children:
                        bar_2_1: {}
        baz:
            children:
                baz_2: 
                    children:
                        baz_2_1:
                            children:
                                baz_2_1_1: {}

YAML
            ,
            ['baz_2_1_1', 'bar_2_1', 'baz_2_1', 'bar_2', 'baz_2', 'bar', 'baz', 'foo']
        ];
    }
}
