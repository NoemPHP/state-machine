<?php

declare(strict_types=1);

namespace Noem\State\Transition;

use Noem\State\StateInterface;
use Noem\State\StateMachineInterface;

class AggregateTransitionProvider implements TransitionProviderInterface
{
    private array $providers;

    public function __construct(TransitionProviderInterface ...$providers)
    {
        $this->providers = $providers;
    }

    public function getTransitionForTrigger(
        StateInterface $state,
        object $trigger,
        StateMachineInterface $stateMachine
    ): ?TransitionInterface {
        foreach ($this->providers as $provider) {
            if (($t = $provider->getTransitionForTrigger($state, $trigger, $stateMachine)) !== null) {
                return $t;
            }
        }
        return null;
    }
}
