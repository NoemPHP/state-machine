<?php

namespace Noem\State\Feature\ExtendedState;

use Noem\State\Chains;
use Noem\State\Chains\Params;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
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
        private readonly BoundAccess $boundAccess
    ) {
    }

    /**
     * @param string $key Key to fetch
     *
     * @return mixed Returns value associated with the requested key or null if no region found
     */
    public function &__get(string $key): mixed
    {
        $result = $this->boundAccess->call(
            new BoundAccessParams($this->region, BoundAccessParams::TYPE_PROPERTY, $key)
        );

        return $result;
    }

    /**
     * @param string $key Key to save the value under
     * @param mixed $value Value to assign
     */
    public function __set(string $key, mixed $value): void
    {
        $this->boundAccess->call(
            new BoundAccessParams($this->region, BoundAccessParams::TYPE_PROPERTY, $key, $value)
        );
    }

    /**
     * @param string $key Key to look up
     *
     * @return mixed Returns the matched value or null if not found
     */
    public function &__call(string $key, array $arguments): mixed
    {
        $result = $this->boundAccess->call(
            new BoundAccessParams(
                $this->region,
                BoundAccessParams::TYPE_METHOD,
                $key,
                $arguments
            )
        );

        return $result;
    }

    /**
     * Sets a given value across all regions within the stack, only when the key does not inherit from any cascaded
     * context.
     *
     * @param string $key Target key to associate the provided value with
     * @param mixed $value Desired value
     */
    //public function set(string $key, mixed $value): void
    //{
    //    $this->set->call(
    //        new Params\Set(
    //            $this->region,
    //            $key,
    //            $value
    //        )
    //    );
    //}

    /**
     *
     * @param object $event
     *
     * @return void
     */
    //public function dispatch(object $event): void
    //{
    //    if ($this->regionStack->count()) {
    //        $current = $this->regionStack->bottom();
    //        assert($current instanceof Region);
    //
    //        $current->onDispatch($event);
    //    }
    //}

    public function __toString(): string
    {
        return $this->region->path();
    }
}
