<?php

declare(strict_types=1);

namespace Noem\State\Middleware;

use ArrayAccess;
use Iterator;

/**
 * The Mesh class implements both ArrayAccess and Iterator interfaces,
 * providing a flexible data structure that behaves like an array but with additional middleware capabilities.
 *
 * It uses the Chain pattern to manage operations such as offsetExists, offsetGet, offsetSet, and offsetUnset.
 * Each operation can be extended or modified by adding middleware functions to the respective chains.
 *
 * The Mesh class is particularly useful in scenarios where you need a dynamic array-like structure
 * with the ability to intercept and modify data access operations. This makes it suitable for complex
 * data management tasks, such as caching, logging, or applying transformations to data elements.
 *
 * @template TKey
 * @template TValue
 *
 * @template-implements ArrayAccess<TKey, TValue>
 * @template-implements Iterator<TKey, TValue>
 */
class Mesh implements ArrayAccess, Iterator
{

    private mixed $position = 0;

    private Chain $offsetExistsChain;

    private Chain $offsetGetChain;

    private Chain $offsetSetChain;

    private Chain $offsetUnsetChain;

    public function __construct(private ?iterable $data = [])
    {
        $this->offsetExistsChain = new Chain()->withProvider(function ($offset) {
            return isset($this->data[$offset]);
        });

        $this->offsetGetChain = new Chain()->withProvider(function ($offset) {
            return $this->data[$offset] ?? null;
        });

        $this->offsetSetChain = new Chain()->withProvider(function ($context) {
            if (is_null($context->offset)) {
                $this->data[] = $context->value;
            } else {
                $this->data[$context->offset] = $context->value;
            }
        });

        $this->offsetUnsetChain = new Chain()->withProvider(function ($offset) {
            unset($this->data[$offset]);
        });
    }

    public function offsetExists($offset): bool
    {
        return ($this->offsetExistsChain)->call($offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->offsetGetChain->call($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->offsetSetChain->call((object)['offset' => $offset, 'value' => $value]);
    }

    public function offsetUnset($offset): void
    {
        ($this->offsetUnsetChain)->call($offset);
    }

    public function current(): mixed
    {
        return $this->data[$this->position];
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function valid(): bool
    {
        return isset($this->data[$this->position]);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function extend(array|ArrayAccess &$extension): void
    {
        $this->offsetExistsChain->link(function (mixed $offset, callable $next) use (&$extension): bool {
            return $next($offset) || isset($extension[$offset]);
        });

        $this->offsetGetChain->link(function (mixed $offset, callable $next) use (&$extension): mixed {
            if (isset($extension[$offset])) {
                return $extension[$offset];
            }

            return $next($offset);
        });

        $this->offsetSetChain->link(function (object $context, callable $next) use (&$extension): void {
            if ($extension instanceof ArrayAccess || is_array($extension)) {
                $extension[$context->offset] = $context->value;
            } else {
                $next($context);
            }
        });

        $this->offsetUnsetChain->link(function (mixed $offset, callable $next) use (&$extension): void {
            if ($extension instanceof ArrayAccess && is_array($extension)) {
                unset($extension[$offset]);
            } else {
                $next($offset);
            }
        });
    }
}
