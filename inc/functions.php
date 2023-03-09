<?php

declare(strict_types=1);

namespace Noem\State;

/**
 * Retrieve the root state of the given nested state
 *
 * @param NestedStateInterface $state
 *
 * @return HierarchicalStateInterface
 */
function root(NestedStateInterface $state): HierarchicalStateInterface
{
    while ($state->parent() !== null) {
        $state = $state->parent();
    }
    assert($state instanceof HierarchicalStateInterface);

    return $state;
}

/**
 * Try to find a specified parent state by name upwards in the given state hierarchy
 *
 * @param string $parent
 * @param NestedStateInterface $state
 *
 * @return HierarchicalStateInterface
 */
function parent(string $parent, NestedStateInterface $state): HierarchicalStateInterface
{
    $originState = $state;
    while ($state->parent() !== null) {
        $state = $state->parent();
        if ($state->equals($parent)) {
            assert($state instanceof HierarchicalStateInterface);

            return $state;
        }
    }
    throw new \RuntimeException(
        "{$parent} is not a parent state of {$originState}"
    );
}

/**
 * Returns the entire branch from the given state until the root parent
 *
 * @param NestedStateInterface $state
 *
 * @return \Traversable
 */
function branch(NestedStateInterface $state): \Traversable
{
    yield $state;
    while (($state = $state->parent()) !== null) {
        yield $state;
    }
}

function bubble(NestedStateInterface $state, callable $callback): void
{
    foreach (branch($state) as $node) {
        $callback($node);
    }
}

function cascade(NestedStateInterface $state, callable $callback): void
{
    foreach (array_reverse(iterator_to_array(branch($state))) as $node) {
        $callback($node);
    }
}

