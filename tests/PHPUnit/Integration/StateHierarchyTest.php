<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

class StateHierarchyTest extends StateMachineTestCase
{

    public function testSimple()
    {
        $this->configureStateMachine(
            [
                'root' => [
                    'children' => [
                        'foo' => [
                            'transitions' => ['bar'],
                        ],
                        'bar' => ['label' => ''],
                    ],
                ],
            ],
            'foo'
        );

        $sut = $this->getStateMachine();

        $this->assertIsInState('root');
        $this->assertIsInState('foo');

        $sut->trigger((object) ['foo' => 'bar']);

        $this->assertIsInState('root');
        $this->assertIsInState('bar');
    }
}