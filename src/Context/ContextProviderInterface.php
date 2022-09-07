<?php

namespace Noem\State\Context;

use Noem\State\ContextInterface;
use Noem\State\StateInterface;

/**
 * Abstraction for providing context objects mapped to a given state
 */
interface ContextProviderInterface
{
    /**
     * Create a new context for the given state.
     * This allows implementors to set a preconfigured context from arbitrary data sources, which might be preferable
     * to initializing the state through entry callbacks inside the FSM
     * @param StateInterface $state
     * @param object $trigger
     *
     * @return ContextInterface
     */
    public function createContext(StateInterface $state, object $trigger): ContextInterface;
}
