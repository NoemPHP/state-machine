<?php

declare(strict_types=1);

namespace Noem\State\Util;

trait Invoker
{
    private $cache = [];

    public function invoke(callable $callable): mixed
    {
        $parameters = ParameterDeriver::getParameterCount($callable);
        $collectorArgs = [];
        for ($i = 0; $i < $parameters; $i++) {
            $type = ParameterDeriver::getParameterType($callable, $i);
            if (!array_key_exists($type, $this->cache)) {
                $this->cache[$type] = $this->patch($type, ParameterDeriver::isParameterNullable($callable, $i));
            }
            $collectorArgs[] = $this->cache[$type];
        }

        return $callable(...$collectorArgs);
    }

    abstract protected function patch(string $key, bool $nullable = false): mixed;
}
