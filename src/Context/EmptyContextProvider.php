<?php

declare(strict_types=1);

namespace Noem\State\Context;

use Noem\State\ContextInterface;
use Noem\State\StateInterface;

class EmptyContextProvider implements ContextProviderInterface
{
    public function createContext(StateInterface $state, object $trigger): ContextInterface
    {
        return new Context($trigger);
    }
}
