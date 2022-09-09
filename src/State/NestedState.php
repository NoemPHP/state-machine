<?php

declare(strict_types=1);

namespace Noem\State\State;

use Noem\State\NestedStateInterface;
use Noem\State\StateInterface;

abstract class NestedState extends SimpleState implements NestedStateInterface
{
    public function __construct(string $id, private ?StateInterface $parent = null)
    {
        parent::__construct($id);
    }

    public function parent(): ?StateInterface
    {
        return $this->parent;
    }

    public function setParent(StateInterface $parent): self
    {
        $this->parent = $parent;

        return $this;
    }
}
