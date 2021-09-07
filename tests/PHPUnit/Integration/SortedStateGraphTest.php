<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use Noem\State\Iterator\AscendingStateIterator;
use Noem\State\Iterator\DepthSortedStateIterator;
use Noem\State\Iterator\ParallelDescendingIterator;
use Noem\State\Loader\Tests\LoaderTestCase;
use Noem\State\NestedStateInterface;
use Noem\State\State\StateDefinitions;

class SortedStateGraphTest extends LoaderTestCase
{

    /**
     * @dataProvider stateTree
     * @throws \JsonException
     */
    public function testIterator(string $states, array $expectedResults, string $initialState)
    {
        $map = $this->configureLoader($states)->definitions();
        /**
         * The following is more of an integration test, but everything relevant is already here, so let's
         * quickly test this here...
         *
         * @var NestedStateInterface $randomState
         */
        $randomState = $map->get($initialState);
        $deepestSubState = DepthSortedStateIterator::getDeepestSubState($randomState);
        $sut = new DepthSortedStateIterator(
            new \CachingIterator(
                new ParallelDescendingIterator(
                    new AscendingStateIterator($deepestSubState)
                )
            )
        );
        $result = iterator_to_array($sut);
        $this->assertSame(
            $expectedResults,
            array_keys($result),
            'Should be able to sort a state tree starting from a random element'
        );
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
            ['baz', 'bar', 'foo'],
            'bar',
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
            ['baz_2_1_1', 'baz_2_1', 'baz_2', 'baz', 'foo'],
            'baz_2_1',
        ];
    }
}
