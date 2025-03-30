<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

use Noem\State\Middleware\Mesh;

class OrnamentResolver
{
    /**
     * @var callable
     */
    private $onResolve;

    public function __construct(
        private readonly Mesh $mesh,
        callable $onResolve
    ) {
        $this->onResolve = $onResolve;
    }

    public function resolve(mixed $result): void
    {
        ($this->onResolve)($result);
    }

    public function get(string $offset): mixed
    {
        return $this->mesh->offsetGet($offset);
    }

    public function has(mixed $offset): bool
    {
        return $this->mesh->offsetExists($offset);
    }
}
