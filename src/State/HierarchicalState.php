<?php

declare(strict_types=1);

namespace Noem\State\State;

use Noem\State\HierarchicalStateInterface;
use Noem\State\StateInterface;

class HierarchicalState implements HierarchicalStateInterface
{
    /**
     * @var StateInterface[]
     */
    private array $children;

    private ?StateInterface $initial = null;

    public function __construct(private string $id, private ?StateInterface $parent = null, StateInterface ...$children)
    {
        $this->children = $children;
    }

    public function children(): array
    {
        return $this->children;
    }

    public function equals(string|StateInterface $otherState): bool
    {
        return $this->id === (string) $otherState;
    }

    public function __toString(): string
    {
        return $this->id;
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

    public function initial(): ?StateInterface
    {
        return $this->initial;
    }

    public function setInitial(StateInterface $state)
    {
        $this->initial = $state;
    }
}
