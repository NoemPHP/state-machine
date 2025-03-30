<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template;

use Noem\State\Feature\Template\Compiler\Invocation;
use Noem\State\Middleware\Mesh;

class Helpers extends Mesh
{
    private array $helpers = [];

    public function __construct()
    {
        $this->registerHelper('each', function (Invocation $data, callable $next) {
            $element = $data->args[0];
            $innerData = $data->data[$element];
            foreach ($innerData as $thing) {
                $newData = $data->withData(['this' => $thing]);
                yield from $next($newData);
            }
        });
        $this->registerHelper('if', function (Invocation $data, callable $next) {
            if ($data->args[0]) {
                yield from $next($data);
            }
        });
        $this->registerHelper('else', function (Invocation $data, callable $next) {
            if (!$data->args[0]) {
                yield from $next($data);
            }
        });

        parent::__construct($this->helpers);
    }

    public function registerHelper(string $name, callable $callback): self
    {
        $this->helpers[$name] = $callback;

        return $this;
    }
}
