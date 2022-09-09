<?php

declare(strict_types=1);

namespace Noem\State\State;

use Noem\State\ParallelStateInterface;
use Noem\State\StateInterface;

class ParallelState extends NestedState implements ParallelStateInterface
{

    public function __construct(string $id, ?StateInterface $parent = null, StateInterface ...$children)
    {
        parent::__construct($id, $parent, ...$children);
    }
}
