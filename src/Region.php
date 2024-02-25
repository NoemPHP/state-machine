<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Util\ParameterDeriver;

class Region
{
    private string $currentState;

    private array $regions;

    private array $transitions;

    /**
     * @var \Closure[][]
     */
    private array $actionHandlers = [];

    /**
     * @var callable[][]
     */
    private array $entryHandlers = [];

    /**
     * @var callable[][]
     */
    private array $exitHandlers = [];

    private array $receives;

    public function __construct(
        private readonly array $states,
        ?string $initial = null
    ) {
        $this->currentState = $initial ?? current($this->states);
    }

    public function id(): string
    {
    }

    public function trigger(object $payload, Context $context): object
    {
        if (isset($this->actionHandlers[$this->currentState])) {
            foreach ($this->actionHandlers[$this->currentState] as $actionHandler) {
                if (!$this->isValidCallback($actionHandler, $payload)) {
                    continue;
                }
                $actionHandler->call($context->extendedState, $payload);
            }
        }
        foreach ($regions = $this->regions() as $region) {
            $context->push($region);
            $region->trigger($payload, $context);
            $context->pop();
        }
        /**
         * We cannot transition away before all regions have finished
         */
        foreach ($regions as $region) {
            if (!$region->isFinal()) {
                return $payload;
            }
        }

        if (isset($this->transitions[$this->currentState])) {
            foreach ($this->transitions[$this->currentState] as $target => $guard) {
                if (!$this->isValidCallback($guard, $payload)) {
                    continue;
                }
                if ($guard($payload)) {
                    $this->doTransition($target);
                    break;
                }
            }
        }

        return $payload;
    }

    private function isValidCallback(callable $callback, object $payload): bool
    {
        $parameterType = ParameterDeriver::getParameterType($callback);
        if ($parameterType !== 'object' && !$payload instanceof $parameterType) {
            return false;
        }

        return true;
    }

    /**
     * @return Region[]
     */
    private function regions(): array
    {
        if (!isset($this->regions[$this->currentState])) {
            return [];
        }

        return $this->regions[$this->currentState];
    }

    private function doTransition(string $to)
    {
        $this->currentState = $to;
    }

    public function setKeysToReference(array $keys)
    {
        $this->receives = $keys;
    }

    public function references(string $key): bool
    {
        return in_array($this->receives);
    }

    public function isFinal(): bool
    {
        return true;
    }

    public function pushRegion(string $state, Region $region)
    {
        $this->regions[$state][] = $region;
    }

    public function pushTransition(string $from, string $to, callable $guard)
    {
        $this->transitions[$from][$to] = $guard;
    }

    public function markFinal(string $state)
    {
    }

    public function isInState(string $state)
    {
        return $this->currentState === $state;
    }

    public function onAction(string $state, callable $callback): static
    {
        $this->actionHandlers[$state][] = $callback;

        return $this;
    }
}
