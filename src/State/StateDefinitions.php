<?php

declare(strict_types=1);

namespace Noem\State\State;

use Noem\State\NestedStateInterface;
use Noem\State\StateInterface;

class StateDefinitions
{

    /**
     * StateTree constructor.
     *
     * @param StateInterface[] $tree
     */
    public function __construct(private array $tree)
    {
    }

    public function has(string $id): bool
    {
        return isset($this->tree[$id]);
    }

    public function get(string $id): StateInterface
    {
        return $this->tree[$id];
    }

    public function getDepth(string $state): int
    {
        $state = $this->get($state);
        if (!$state instanceof NestedStateInterface) {
            return 0;
        }
        $i = 0;
        while ($state = $state->parent()) {
            $i++;
        }

        return $i;
    }

    private function getLayer(int $layerDepth)
    {
        $layer = [];
        foreach ($this->tree as $item) {
            if ($this->getDepth((string)$item) == $layerDepth) {
                $layer[] = $item;
            }
        }

        return $layer;
    }

    public function initial(): StateInterface
    {
        // TODO Try to actually read some initial flag from the tree
        return current($this->getLayer(0));
    }
}
