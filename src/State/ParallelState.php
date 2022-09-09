<?php

declare(strict_types=1);

namespace Noem\State\State;

use Noem\State\ParallelStateInterface;
use Noem\State\StateInterface;
use Noem\State\StateMachine;
use Noem\State\StateMachineInterface;

class ParallelState extends NestedState implements ParallelStateInterface
{

    /**
     * @var StateMachine[]
     */
    private array $regions;

    public function __construct(string $id, ?StateInterface $parent = null, StateMachineInterface ...$regions)
    {
        $this->regions = $regions;
        parent::__construct($id, $parent);
    }

    public function regions(): array
    {
        return $this->regions;
    }
}
