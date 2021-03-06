<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Iterator;

use Noem\State\Loader\Tests\LoaderTestCase;
use Noem\State\State\StateTree;

class StateTreeTest extends LoaderTestCase
{

    /**
     * @dataProvider provideTestData
     * @return void
     * @throws \JsonException
     */
    public function testExistsInBranch(
        string $yaml,
        string $initialStateName,
        string $leaf,
        bool $expected
    ) {
        $map = $this->configureLoader($yaml)->definitions();
        $initialState = $map->get($initialStateName);
        $leafState = $map->get($leaf);
        $sut = new StateTree($initialState);
        $this->assertSame($expected, $sut->existsInBranch($leafState));
    }

    public function provideTestData(): \Generator
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
            'bar',
            'baz',
            true,
        ];

        yield '#2 branching hierarchy' => [
            <<<YAML

foo:
    initial: bar2
    children:
        bar:
            children:
                baz: {}
        bar2:
            children:
                baz2: {}

YAML
            ,
            'bar',
            'baz',
            true,
        ];

        yield '#3 parallel hierarchy' => [
            <<<YAML

foo:
    parallel: true
    children:
        bar:
            children:
                bar_2:
                    children:
                        bar_2_1: {}
        baz:
            children:
                baz_2: 
                    initial: baz_2_2
                    children:
                        baz_2_1:
                            children:
                                baz_2_1_1: {}
                        baz_2_2:
                            children:
                                baz_2_2_1: {}
    
YAML
            ,
            'baz_2_1',
            'baz_2_2_1',
            false,
        ];
    }
}
