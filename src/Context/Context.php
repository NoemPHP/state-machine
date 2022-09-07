<?php

declare(strict_types=1);

namespace Noem\State\Context;

use Noem\State\ImmutableContextInterface;
use Noem\State\ContextInterface;

class Context extends \ArrayObject implements ImmutableContextInterface
{
    public function __construct(private object $trigger)
    {
        parent::__construct();
    }

    public function trigger(): object
    {
        return $this->trigger;
    }

    public function clear(): ContextInterface
    {
        $this->exchangeArray([]);

        return $this;
    }

    public function replace(array $data): ContextInterface
    {
        $this->exchangeArray($data);

        return $this;
    }

    public function withTrigger(object $trigger): ImmutableContextInterface
    {
        $new = clone $this;
        $new->trigger = $trigger;

        return $new;
    }
}