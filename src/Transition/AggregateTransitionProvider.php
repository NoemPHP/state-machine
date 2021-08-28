<?php

declare(strict_types=1);


namespace Noem\State\Transition;


use Noem\State\StateInterface;

class AggregateTransitionProvider implements TransitionProviderInterface
{
    private array $providers;

    public function __construct(TransitionProviderInterface ...$providers)
    {
        $this->providers = $providers;
    }

    public function getTransitionForTrigger(StateInterface $state, object $trigger): ?TransitionInterface
    {
        foreach ($this->providers as $provider) {
            if (($t = $provider->getTransitionForTrigger($state, $trigger)) !== null) {
                return $t;
            }
        }
        return null;
    }
}
