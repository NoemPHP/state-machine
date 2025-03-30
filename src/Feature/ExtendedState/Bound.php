<?php

namespace Noem\State\Feature\ExtendedState;

use Noem\State\Chains;
use Noem\State\Chains\Params;
use Noem\State\Region;

/**
 * Represents the context that state machine event handlers are bound to.
 * In other words, it's their "$this"
 * The main purpose of this class is to wire up the specific handler context to the chainmail
 * backend that actually manages the extended state for the current region.
 */
class Bound implements \Stringable
{
    public function __construct(
        private readonly Region $region,
        private readonly Chains\Get $get,
        private readonly Chains\Set $set
    ) {
    }

    /**
     *  @param string $key Key to fetch
     *
     * @return mixed Returns value associated with the requested key or null if no region found
     */
    public function &__get(string $key): mixed
    {
        $call = $this->get->call(
            new Params\Get(
                $this->region,
                $key,
                $this->region->currentState()
            )
        );

        return $call;
    }

    /**
     * @param string $key Key to save the value under
     * @param mixed $value Value to assign
     */
    public function __set(string $key, mixed $value): void
    {
        $this->set->call(
            new Params\Set(
                $this->region,
                $key,
                $value,
                false
            )
        );
    }

    /**
     * @param string $key Key to look up
     *
     * @return mixed Returns the matched value or null if not found
     */
    public function &get(string $key): mixed
    {
        $call = $this->get->call(
            new Params\Get(
                $this->region,
                $key
            )
        );

        return $call;
    }

    /**
     * Sets a given value across all regions within the stack, only when the key does not inherit from any cascaded
     * context.
     *
     * @param string $key Target key to associate the provided value with
     * @param mixed $value Desired value
     */
    public function set(string $key, mixed $value): void
    {
        $this->set->call(
            new Params\Set(
                $this->region,
                $key,
                $value
            )
        );
    }

    /**
     *
     * @param object $event
     *
     * @return void
     */
    public function dispatch(object $event): void
    {
        if ($this->regionStack->count()) {
            $current = $this->regionStack->bottom();
            assert($current instanceof Region);

            $current->onDispatch($event);
        }
    }

    public function __toString(): string
    {
        return $this->region->path();
    }
}
