<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template\Compiler;

use Noem\App\AsyncTemplate\Capture;
use Noem\App\AsyncTemplate\Helper\Helper;
use Noem\App\AsyncTemplate\Rendered;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

class Template
{
    private array $tree = [];

    /**
     * @var string|PromiseInterface[]
     */
    private array $stack = [];

    /**
     * @var Deferred[]
     */
    private array $next = [];

    /**
     * @var true
     */
    private bool $isSealed = false;

    /**
     * @param Helper[] $helpers
     */
    public function __construct(private readonly ?array $helpers = [])
    {
        $this->previous = resolve('');
    }

    public function helper(string $name, $args): PromiseInterface
    {
        if (!isset($this->helpers[$name])) {
            return resolve('');
        }

        $deferred = new Deferred();
        $this->helpers[$name]->resolve(
            $this,
            $args,
            $this->promisePrefix(-1),
            $this->promiseSuffix(25)
        )->then(function ($result) use ($deferred) {
            if ($result instanceof Capture) {
                $this->capture($result->name, $result->data);
                $deferred->resolve($result->data);

                return;
            }
            $deferred->resolve($result);
        });

        return $deferred->promise();
    }

    public function capture($path, mixed $data, $i = 0): void
    {
        //if they are asking for the parent
        if (strpos($path, '../') === 0) {
            $this->capture(substr($path, 3), $data, $i + 1);
            return;
        }

        if (strpos($path, './') === 0) {
            $this->capture(substr($path, 2), $data, $i);
            return;
        }
        //separate by .
        $path = explode('.', $path);
        $last = count($path) - 1;
        $current = &$this->tree[$i];

        foreach ($path as $i => $node) {
            if ($i === $last) {
                if (str_ends_with($node, '[]')) {
                    $key = str_replace('[]', '', $node);
                    if (!isset($current[$key])) {
                        $current[$key] = [];
                    }
                    if (is_array($current[$key])) {
                        $current[$key][] = $data;

                        return;
                    }
                }
                $current[$node] = $data;
            }

            if (!isset($current[$node])) {
                $current[$node] = [];
            }
            $current = &$current[$node];
        }
    }

    public function find($path, $i = 0): PromiseInterface
    {
        if ($i >= count($this->tree)) {
            return reject(new \Exception("Value '{$path}' not found"));
        }

        $current = $this->tree[$i];

        //if they are asking for the parent
        if (strpos($path, '../') === 0) {
            return $this->find(substr($path, 3), $i + 1);
        }

        if (strpos($path, './') === 0) {
            return $this->find(substr($path, 2), $i);
        }

        //separate by .
        $path = explode('.', $path);
        $last = count($path) - 1;

        foreach ($path as $i => $node) {
            //is it the last ?
            if ($i === $last) {
                //does it exist?
                if (is_object($current) && property_exists($current, $node)) {
                    return resolve($current->$node);
                }
                if (is_array($current) && isset($current[$node])) {
                    return resolve($current[$node]);
                }

                //is it length ?
                if ($node === 'length') {
                    //is it a string?
                    if (is_string($current)) {
                        return resolve(strlen($current));
                    }

                    //is it an array?
                    if (is_array($current) || $current instanceof \Countable) {
                        return resolve(count($current));
                    }

                    //we cant count it, so it's 0
                    return resolve(0);
                }

                return resolve('');
            }

            //we are not at the last node...
            //does the node exist and is it an array ?
            if (isset($current[$node]) && is_array($current[$node])) {
                //great we can continue
                $current = $current[$node];
                continue;
            }

            if (isset($current[$node]) && is_object($current[$node])) {
                //great we can continue
                $current = $current[$node];
                continue;
            }

            //if it exists and we are just getting the length
            if (isset($current[$node]) && $path[$i + 1] === 'length' && ($i + 1) === $last) {
                //let it continue
                $current = $current[$node];
                continue;
            }

            //if we are here, then there maybe a node in current,
            //but there's still more nodes to process
            //either way it cannot be what we are searching for
            break;
        }

        return resolve('');
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string)$value;
        }
        if (is_array($value)) {
            return json_encode($value);
        }

        return get_class($value);
    }

    public function get()
    {
        return $this->tree[0];
    }

    public function push(array $data)
    {
        array_unshift($this->tree, $data);

        return $this;
    }

    public function pop()
    {
        array_shift($this->tree);

        return $this;
    }

    public function stringify(PromiseInterface $promise): PromiseInterface
    {
        $deferred = new Deferred();
        $promise->then(function ($result) use ($deferred) {
            return $deferred->resolve(
                $this->stringifyValue($result)
            ); // escaping currently unneeded since we're never leaving PHP
        });

        return $deferred->promise();
    }

    public function escape(PromiseInterface $promise): PromiseInterface
    {
        $deferred = new Deferred();
        $promise->then(function ($result) use ($deferred) {
            return $deferred->resolve($result); // escaping currently unneeded since we're never leaving PHP
            //$deferred->resolve(htmlspecialchars((string)$result, ENT_COMPAT, 'UTF-8'));
        });

        return $deferred->promise();
    }

    public function append(string|PromiseInterface $promiseOrChunk): void
    {
        $currentIndex = count($this->stack);
        if (isset($this->next[$currentIndex])) {
            /**
             * Apparently we need to wrap this in an array because listeners
             * will not be called if you resolve with another promise
             */
            $this->next[$currentIndex]->resolve([$promiseOrChunk]);
            unset($this->next[$currentIndex]);
        }
        $this->stack[] = $promiseOrChunk;
    }

    public function seal()
    {
        $this->isSealed = true;
        foreach ($this->next as $deferred) {
            $deferred->resolve('');
        }
    }

    public function promise(): PromiseInterface
    {
        $deferred = new Deferred();
        all($this->stack)->then(function ($resolved) use ($deferred) {
            $rendered = new Rendered(
                implode('', $resolved),
                $this->tree[0]
            );
            $deferred->resolve($rendered);
        });

        return $deferred->promise();
    }

    public function promisePrefix(int $offset): PromiseInterface
    {
        $deferred = new Deferred();
        $position = count($this->stack) - 1;
        $out = '';
        $loop = function (string &$out, &$position, &$loop, Deferred $deferred) use ($offset) {
            if ($position < 0) {
                $deferred->resolve($out);

                return;
            }
            $promise = $this->stack[$position];
            if ($promise instanceof PromiseInterface) {
                $promise->then(function ($text) use (&$out, &$position, &$loop, $offset, $deferred) {
                    $out = $text . $out;
                    if ($offset > 0 && strlen($out) > $offset) {
                        $deferred->resolve($out);

                        return;
                    }
                    $position--;
                    $loop($out, $position, $loop, $deferred);
                });

                return;
            }
            $out = $promise . $out;
            if ($offset > 0 && strlen($out) > $offset) {
                $deferred->resolve($out);

                return;
            }
            $position--;
            $loop($out, $position, $loop, $deferred);
        };
        $loop($out, $position, $loop, $deferred);

        return $deferred->promise();
    }

    public function promiseIndex(int $index): PromiseInterface
    {
        if (isset($this->stack[$index])) {
            return $this->stack[$index];
        }
        if ($this->isSealed) {
            return resolve(['']);
        }
        if (!isset($this->next[$index])) {
            $this->next[$index] = new Deferred();
        }

        return $this->next[$index]->promise();
    }

    public function promiseSuffix(int $offset): PromiseInterface
    {
        $deferred = new Deferred();
        $position = count($this->stack);
        $out = '';
        $loop = function (string &$out, &$position, &$loop, Deferred $deferred) {
            if ($this->isSealed) {
                $deferred->resolve($out);

                return;
            }
            $promise = $this->promiseIndex($position);
            $promise->then(function ($resolved) use (&$out, &$position, &$loop, $deferred) {
                [$resolved] = $resolved;
                if ($resolved instanceof PromiseInterface) {
                    /**
                     * Currently, waiting for both prefix and suffix promises can result in deadlocks!
                     * The workaround is that async execution is ignored when creating the suffix.
                     * This is not a problem that can be solved with better code I guess.
                     * But in the future, it might be worth it to add some parameter that
                     * allows skipping EITHER suffix or prefix promises
                     */
                    $position++;
                    $loop($out, $position, $loop, $deferred);

                    return;
                }
                $out = $out . $resolved;
                if (strlen($out) > 25) {
                    $deferred->resolve($out);

                    return;
                }
                $position++;
                $loop($out, $position, $loop, $deferred);
            });
        };
        $loop($out, $position, $loop, $deferred);

        return $deferred->promise();
    }
}
