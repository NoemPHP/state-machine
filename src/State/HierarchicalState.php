<?php

declare(strict_types=1);

namespace Noem\State\State;

use Noem\State\HierarchicalStateInterface;
use Noem\State\StateInterface;

class HierarchicalState extends NestedState implements HierarchicalStateInterface
{

    private ?StateInterface $initial = null;

    public function __construct(string $id, ?StateInterface $parent = null, StateInterface ...$children)
    {
        parent::__construct($id, $parent, ...$children);
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
