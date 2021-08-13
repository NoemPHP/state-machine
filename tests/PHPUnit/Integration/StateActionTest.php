<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use Noem\State\InMemoryStateStorage;
use Noem\State\EventManager;
use Noem\State\Loader\Tests\StateMachineTestCase;
use Noem\State\State\SimpleState;
use Noem\State\State\StateDefinitions;
use Noem\State\StateMachine;
use Noem\State\Transition\TransitionProvider;
use PHPUnit\Framework\TestCase;

class StateActionTest extends StateMachineTestCase
{

    public function testAction()
    {
        $sut = $this->configureStateMachine(
            [
                'off' => [
                    'action' => '@offAction',
                ],
            ],
            'off',
            [
                'offAction' => function (\stdClass $payload) {
                    $payload->handled = true;
                },
            ]
        );
        $payload = (object) ['handled' => false];
        $result = $sut->action($payload);
        $this->assertTrue($result->handled);
    }
}
