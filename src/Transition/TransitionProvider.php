<?php

declare(strict_types=1);

namespace Noem\State\Transition;

use Noem\State\Exception\StateMachineExceptionInterface;
use Noem\State\State\StateDefinitions;
use Noem\State\StateInterface;
use Noem\State\StateMachineInterface;

class TransitionProvider implements TransitionProviderInterface
{
    private array $transitions;

    /**
     * GuardCapableTransitionProvider constructor.
     *
     * @param TransitionInterface[] $transitionList
     */
    public function __construct(private StateDefinitions $tree, TransitionInterface ...$transitionList)
    {
        $this->transitions = $transitionList;
    }

    public function getTransitionForTrigger(
        StateInterface $state,
        object $trigger,
        StateMachineInterface $stateMachine
    ): ?TransitionInterface
    {
        foreach ($this->transitions as $possibleTransition) {
            if (!$possibleTransition->source()->equals($state)) {
                continue;
            }
            if (!$possibleTransition->isEnabled($trigger, $stateMachine)) {
                continue;
            }

            return $possibleTransition;
        }

        return null;
    }

    /**
     * @param string $from
     * @param string $to
     * @param string|callable|null $triggerNameOrGuard Either a FQCN of the event, or a guard callback
     *
     * @return $this
     */
    public function registerTransition(string $from, string $to, string|callable|null $triggerNameOrGuard = null): self
    {
        if (!$this->tree->has($from) || !$this->tree->has($to)) {
            throw new class (sprintf(
                'There is no transition from "%s" to "%s"',
                $from,
                $to
            )) extends \Exception implements StateMachineExceptionInterface {
            };
        }
        $transition = new SimpleTransition($this->tree->get($from), $this->tree->get($to));
        if ($triggerNameOrGuard) {
            $transition = is_callable($triggerNameOrGuard)
                ? new GuardedTransition($transition, $triggerNameOrGuard)
                : new EventEnabledTransition($transition, $triggerNameOrGuard);
        }
        $this->transitions[] = $transition;

        return $this;
    }
}
