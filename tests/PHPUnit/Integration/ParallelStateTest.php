<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use Noem\State\Loader\Tests\StateMachineTestCase;

class ParallelStateTest extends StateMachineTestCase
{

    private array $stateGraph = [
        'root' => [
            'children' => [
                'foo' => [
                    'transitions' => ['bar'],
                ],
                'bar' => [
                    'parallel' => true,
                    'children' => [
                        'bar_1' => [
                            'transitions' => ['foo'],
                        ],
                        'bar_2' => [
                            'children' => [
                                'bar_2_1' => ['label' => ''],
                                'bar_2_2' => ['label' => ''],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    public function testEnterParallelSuperState()
    {
        $this->configureStateMachine($this->stateGraph, 'foo');

        $sut = $this->getStateMachine();

        $this->assertIsInState('root');
        $this->assertIsInState('foo');

        $sut->trigger((object) ['foo' => 'bar']);

        $this->assertIsInState('root');
        $this->assertIsInState('bar');
        $this->assertIsInState('bar_1');
        $this->assertIsInState('bar_2');
    }

    public function testExit()
    {
        $this->configureStateMachine($this->stateGraph, 'bar_2');

        $sut = $this->getStateMachine();

        $this->assertIsInState('root');
        $this->assertIsInState('bar_1');
        $this->assertIsInState('bar_2');

        $sut->trigger((object) ['foo' => 'bar']);

        $this->assertNotInState('bar_1');
        $this->assertNotInState('bar_2');
        $this->assertIsInState('foo');
    }
}
