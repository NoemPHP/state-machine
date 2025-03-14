<?php

declare(strict_types=1);

namespace Noem\State\Middleware;

use Override;

/**
 * @template C
 * @template R
 * @template-implements ChainInterface<C,R>
 */
class Chain implements ChainInterface
{
    protected int $maxRestarts = 1; // Maximum number of restarts to prevent infinite loops

    private array $middlewares;

    /**
     * @var callable( C $context, $next, callable $first): R
     */
    private $provider;

    /**
     * @var callable $currentChain
     */
    private $currentChain;

    /**
     * @param ?callable( C $context, $next, callable $first): R $provider
     * @param list<callable( C $context, callable(C $c ): R $next, callable(C $c ): R $first): R> $middlewares
     * @param int|null $maxRestarts
     */
    public function __construct(?callable $provider = null, array $middlewares = [], ?int $maxRestarts = null)
    {
        $this->provider = $provider ?? fn() => null;
        $this->middlewares = $middlewares;
        if (!is_null($maxRestarts)) {
            $this->maxRestarts = $maxRestarts;
        }
    }

    public function withProvider(callable $provider): self
    {
        return new self($provider, $this->middlewares, $this->maxRestarts);
    }

    /**
     * @param callable( C $context, callable(C $c ): R $next, callable(C $c ): R $first): R $callback
     *
     * @return $this
     */
    #[Override] public function link(callable $callback): self
    {
        $this->middlewares[] = $callback;
        $this->currentChain = null;

        return $this;
    }

    public function memoize(?callable $equalityCheck = null): self
    {
        $equalityCheck = $equalityCheck ?? fn($a, $b) => $a === $b; // Default equality check is strict comparison
        array_unshift($this->middlewares, function (mixed $context, callable $next) use ($equalityCheck) {
            static $lastContext;
            static $lastResult;
            if (!isset($lastContext) || !$equalityCheck($lastContext, $context)) {
                $lastResult = $next($context);
                $lastContext = $context;
            }

            return $lastResult; // Return the stored result to memoize it
        });
        $this->currentChain = null;

        return $this;
    }

    /**
     * @param callable(C $context): R $provider
     *
     * @return callable(C $context): R
     */
    private function create(callable $provider): callable
    {
        $middlewares = array_reverse($this->middlewares);
        $first = new class {
            public $callback;

            public function __invoke(mixed $context): mixed
            {
                if (!$this->callback) {
                    throw new \RuntimeException('No callback provided');
                }

                return ($this->callback)($context);
            }
        };

        $next = fn(mixed &$context) => $provider($context);
        foreach ($middlewares as $plugin) {
            $next = function (&$context) use ($plugin, $next, $first) {
                return $plugin($context, $next, $first);
            };
        }

        $chain = $this->withRestartTracking($next);
        $first->callback = $chain;

        return $chain;
    }

    /**
     * @param callable(C $context): R $chain
     *
     * @return callable(C $context): R
     */
    private function withRestartTracking(callable $chain): callable
    {
        $restarts = 0;
        $maxRestarts = $this->maxRestarts;

        return function (mixed $context) use ($chain, &$restarts, $maxRestarts) {
            if ($maxRestarts > 0 && $restarts > $maxRestarts) {
                throw new ChainException('Too many restarts in middleware chain');
            }

            ++$restarts;
            $result = $chain($context);
            --$restarts;

            return $result;
        };
    }

    /**
     * @param C $context
     *
     * @return R
     */
    public function call(mixed $context): mixed
    {
        if (!$this->currentChain) {
            $this->currentChain = $this->create($this->provider);
        }

        return ($this->currentChain)($context);
    }
}
