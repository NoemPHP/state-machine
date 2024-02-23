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
     * @var callable[][]
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

    public function __construct(private readonly array $states, ?string $initial = null)
    {
        $this->currentState = $initial ?? current($this->states);
    }

    public function trigger(object $payload): object
    {
        if (isset($this->actionHandlers[$this->currentState])) {
            foreach ($this->actionHandlers[$this->currentState] as $actionHandler) {
                $parameterType = ParameterDeriver::getParameterType($actionHandler);
                if ($parameterType !== 'object' && !$payload instanceof $parameterType) {
                    continue;
                }
                $actionHandler($payload);
            }
        }
        foreach ($regions = $this->regions() as $region) {
            $region->trigger($payload);
        }

        foreach ($regions as $region) {
            if ($region->isFinal()) {
                return $payload;
            }
        }

        if (isset($this->transitions[$this->currentState])) {
            foreach ($this->transitions[$this->currentState] as $target => $guard) {
                $parameterType = ParameterDeriver::getParameterType($guard);
                if ($parameterType !== 'object' && !$payload instanceof $parameterType) {
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
