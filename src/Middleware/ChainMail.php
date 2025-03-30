<?php

declare(strict_types=1);

namespace Noem\State\Middleware;

use Noem\State\Util\Invoker;
use Noem\State\Util\ParameterDeriver;
use TypeError;

class ChainMail
{
    use Invoker;

    private array $dependencies = [];

    public function __construct()
    {
        $foo = 1;
    }

    public function use(callable $callback, callable ...$dependencies): mixed
    {
        $this->supply(...$dependencies);

        return $this->invoke($callback);
    }

    public function supply(callable ...$dependencies): self
    {
        foreach ($dependencies as $dep) {
            $type = ParameterDeriver::getReturnType($dep);
            if (is_null($type)) {
                throw new TypeError("Dependency providers MUST specify a return type");
            }
            $this->dependencies[$type] = $dep;
        }

        return $this;
    }

    protected function patch(string $key, bool $nullable = false): mixed
    {
        if (!array_key_exists($key, $this->dependencies)) {
            if (!$nullable) {
                throw new TypeError("Dependency '$key' not found.");
            }

            return null;
        }
        return $this->invoke($this->dependencies[$key]);
    }
}
