<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use Noem\State\InMemoryStateStorage;
use Noem\State\Loader\Tests\StateMachineTestCase;
use Noem\State\Observer\EnterStateObserver;
use Noem\State\State\SimpleState;
use Noem\State\State\StateDefinitions;
use Noem\State\StateInterface;
use Noem\State\StateMachine;
use Noem\State\StateMachineInterface;
use Noem\State\Transition\TransitionProvider;
use PHPUnit\Framework\TestCase;

class TransitionTest extends StateMachineTestCase
{

    public function testSimpleTransition()
    {
        $sut = $this->configureStateMachine(
            [
                'off' => [
                    'transitions' => ['on'],
                ],
                'on' => [
                    'transitions' => ['off'],
                ],
            ],
            'off'
        );

        $sut->trigger((object)['foo' => 'bar']);
        $this->assertTrue($sut->isInState('on'));
    }

    public function testSimpleNegative()
    {
        $sut = $this->configureStateMachine(
            [
                'off' => [
                    'transitions' => ['on'],
                ],
                'on' => [
                    'transitions' => ['off'],
                ],
            ],
            'off'
        );

        $sut->trigger((object)['foo' => 'bar']);
        $this->assertNotTrue($sut->isInState('off'));
    }

    public function testNotifyEnterWithGenericObserver()
    {
        $notified = false;

        $sut = $this->configureStateMachine(
            [
                'off' => [
                    'transitions' => ['on'],
                ],
                'on' => [
                    'transitions' => ['off'],
                    'onEntry' => '@onEnter',
                ],
            ],
            'off',
            [
                'onEnter' => function () use (&$notified) {
                    $notified = true;
                },
            ]
        );
        $sut->attach(
            new class($notified) implements EnterStateObserver {

                /** @noinspection PhpPropertyOnlyWrittenInspection */
                public function __construct(private bool &$flag)
                {
                }

                public function onEnterState(StateInterface $state, StateMachineInterface $machine)
                {
                    $this->flag = true;
                }
            }
        );

        $sut->trigger((object)['foo' => 'bar']);
        $this->assertTrue($notified);
    }

    public function testNotifyEnter()
    {
        $notified = false;

        $sut = $this->configureStateMachine(
            [
                'off' => [
                    'transitions' => ['on'],
                ],
                'on' => [
                    'onEntry' => '@onEnter',
                ],
            ],
            'off',
            [
                'onEnter' => function () use (&$notified) {
                    $notified = true;
                },
            ]
        );

        $sut->trigger((object)['foo' => 'bar']);
        $this->assertTrue($notified);
    }

    public function testNotifyEnterOfChild()
    {
        $notified = false;

        $sut = $this->configureStateMachine(
            [
                'off' => [
                    'transitions' => ['on'],
                ],
                'on' => [
                    'parallel' => true,
                    'children' => [
                        'sub_state' => [
                            'onEntry' => '@onEnter',
                        ],
                    ],
                ],
            ],
            'off',
            [
                'onEnter' => function () use (&$notified) {
                    $notified = true;
                },
            ]
        );

        $sut->trigger((object)['foo' => 'bar']);
        $this->assertTrue($notified);
    }

    public function testNotifyEnterNotRecurringForSuperstates()
    {
        $notified = 0;

        $sut = $this->configureStateMachine(
            [
                'off' => [
                    'transitions' => ['on'],
                ],
                'on' => [
                    'onEntry' => '@onEnter',
                    'children' => [
                        'sub_state1' => [
                            'transitions' => ['sub_state2'],
                        ],
                        'sub_state2' => [],

                    ],
                ],
            ],
            'off',
            [
                'onEnter' => function () use (&$notified) {
                    $notified++;
                },
            ]
        );

        $sut->trigger((object)['foo' => 'bar']);
        $sut->trigger((object)['foo' => 'bar']);
        $this->assertSame(1, $notified, 'onEntry callback should only run once if substates change');
    }

    public function testEventEnabled()
    {
        $sut = $this->configureStateMachine(
            [
                'off' => [
                    'transitions' => [
                        [
                            'target' => 'on',
                            'guard' => \DateTimeInterface::class,
                        ],
                    ],
                ],
                'on' => [
                    'transitions' => ['off'],
                ],
            ],
            'off'
        );
        $sut->trigger(new \stdClass());
        $this->assertNotTrue($sut->isInState('on'));

        $sut->trigger(new \DateTime());
        $this->assertTrue($sut->isInState('on'));
    }

    public function testGuardEnabled()
    {
        $stateMap = $this->createStateMap();
        $transitionProvider = new TransitionProvider(
            $stateMap,
        );
        $transitionProvider->registerTransition(
            'off',
            'on',
            function (\DateTime $t) {
                return true;
            }
        );

        $sut = new StateMachine(
            $transitionProvider,
            new InMemoryStateStorage($stateMap->get('off'))
        );

        $sut->trigger(new \DateTime());
        $this->assertTrue($sut->isInState('on'));
    }

    private function createStateMap()
    {
        return new StateDefinitions(
            [
                'off' => new SimpleState('off'),
                'on' => new SimpleState('on'),
            ]
        );
    }
}
