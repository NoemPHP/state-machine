<?php

namespace Noem\State;

class Context implements \Stringable
{
    private bool $isHandlingException = false;

    public function __construct(private \SplStack $regionStack)
    {
    }

    /**
     * Retrieves a specific key from the region stack context.
     * If there is an active region in the stack, access its state context first.
     * Otherwise, returns null.
     *
     * @param string $key Key to fetch
     *
     * @return mixed Returns value associated with the requested key or null if no region found
     */
    public function __get(string $key): mixed
    {
        if ($this->regionStack->count()) {
            $current = $this->regionStack->top();
            assert($current instanceof Region);

            return $current->getStateContext($key);
        }

        return null;
    }

    /**
     * Updates or sets a state context in the innermost region of the stack.
     *
     * @param string $key Key to save the value under
     * @param mixed $value Value to assign
     */
    public function __set(string $key, mixed $value): void
    {
        if ($this->regionStack->count()) {
            $current = $this->regionStack->top();
            assert($current instanceof Region);
            $current->setStateContext($key, $value);
        }
    }

    /**
     * Recursively traverses through stacked regions until finding a matching key-value pair.
     *
     * @param string $key Key to look up
     *
     * @return mixed Returns the matched value or null if not found
     */
    public function get(string $key): mixed
    {
        foreach ($this->regionStack as $region) {
            assert($region instanceof Region);
            if ($region->inherits($key)) {
                continue;
            }

            return $region->getRegionContext($key);
        }

        return null;
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
        foreach ($this->regionStack as $region) {
            assert($region instanceof Region);
            if ($region->inherits($key)) {
                continue;
            }

            $region->setRegionContext($key, $value);
        }
    }

    public function handleException(\Throwable $exception): void
    {
        /**
         * If handling one exception causes another one, we give up
         */
        if ($this->isHandlingException) {
            throw $exception;
        }
        $this->isHandlingException = true;
        foreach ($this->regionStack as $region) {
            assert($region instanceof Region);
            $region->trigger($exception);
        }
        $this->isHandlingException = false;

        $rootRegion = $this->regionStack->top();
        assert($rootRegion instanceof Region);
        if (!$rootRegion->isFinal()) {
            throw $exception;
        }
    }

    public function __toString(): string
    {
        $states = [];
        foreach ($this->regionStack as $region) {
            assert($region instanceof Region);
            $states[] = $region->currentState();
        }

        return implode('.', array_reverse($states));
    }
}
