<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\InMemoryStateStorage;
use Noem\State\Loader\ArrayLoader;
use Noem\State\StateMachine;
use PHPUnit\Framework\Assert;
use Psr\Container\ContainerInterface;

abstract class StateMachineTestCase extends MockeryTestCase
{

    private StateMachine $machine;

    /**
     * @param array $stateGraph
     * @param string $initialState
     * @param array $serviceConfig
     */
    protected function configureStateMachine(
        array $stateGraph,
        string $initialState,
        array $serviceConfig = []

    ): StateMachine {
        $serviceLocator = \Mockery::mock(ContainerInterface::class);
        $serviceLocator->shouldReceive('get')->andReturnUsing(
            function ($id) use ($serviceConfig) {
                return $serviceConfig[$id];
            }
        );
        $loader = new ArrayLoader($stateGraph, $serviceLocator);
        $tree = $loader->definitions();

        $this->machine = new StateMachine(
            $loader->transitions(),
            new InMemoryStateStorage($tree->get($initialState))
        );
        $this->machine->attach($loader->observer());

        return $this->machine;
    }

    protected function getStateMachine(): StateMachine
    {
        return $this->machine;
    }

    protected function assertIsInState(string $state, ?string $message = null)
    {
        $this->assertThat(
            $this->getStateMachine()->isInState($state),
            Assert::isTrue(),
            $message ?? sprintf('The state machine should currently be in state "%s"', $state)
        );
    }

    protected function assertNotInState(string $state, ?string $message = null)
    {
        $this->assertThat(
            $this->getStateMachine()->isInState($state),
            Assert::logicalNot(Assert::isTrue()),
            $message ?? sprintf('The state machine should currently **NOT** be in state "%s"', $state)
        );
    }
}