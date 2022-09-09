<?php

declare(strict_types=1);

namespace Noem\State\State;

use Noem\State\HierarchicalStateInterface;
use Noem\State\StateInterface;

class HierarchicalState extends NestedState implements HierarchicalStateInterface
{
    /**
     * @var StateInterface[]
     */
    private array $children;

    private ?StateInterface $initial = null;

    public function __construct(string $id, ?StateInterface $parent = null, StateInterface ...$regions)
    {
        $this->children = $regions;
        parent::__construct($id, $parent);
    }

    public function children(): array
    {
        return $this->children;
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
