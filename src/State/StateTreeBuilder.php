<?php

declare(strict_types=1);

namespace Noem\State\State;

use Noem\State\StateInterface;

class StateTreeBuilder
{

    private array $states = [];

    private array $hierarchy = [];

    private array $parallel = [];

    public function has(string|StateInterface $state): bool
    {
    }

    public function get(string $id): StateInterface
    {
    }

    public function register(string $state)
    {
    }

    public function makeSubState(string $state, string $parent)
    {
    }

    public function makeParallel(string $state, string $parent)
    {
    }

    public function finalize()
    {
    }
}
