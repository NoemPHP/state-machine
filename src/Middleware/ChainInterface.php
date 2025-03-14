<?php

namespace Noem\State\Middleware;

/**
 * @template C
 * @template R
 */
interface ChainInterface
{
    /**
     * @param callable( C $context, callable(C $context): R $next, callable(C $context): R $first): R $callback
     */
    public function link(callable $callback): self;
}
