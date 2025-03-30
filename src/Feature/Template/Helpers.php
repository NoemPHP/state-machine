<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template;

use Noem\State\Feature\Template\Compiler\Invocation;
use Noem\State\Feature\Template\Compiler\TemplateFactory;
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
                $newData = $data->setData(['this' => $thing]);
                yield from $next($newData);
            }
        });
        $this->registerHelper('if', function (Invocation $data, callable $next) {
            if ($data->args[0]) {
                return yield from $next($data);
            }
            return yield '';
        });
        $this->registerHelper('else', function (Invocation $data, callable $next) {
            if (!$data->args[0]) {
                return yield from $next($data);
            }
            return yield '';
        });

        $this->registerHelper('include', function (Invocation $data, callable $next) {
            $fragment = $data->args[0];
            if (!$fragment) {
                return yield from $next($data);
            }
            $dir = getcwd();
            $template = file_get_contents($dir . '/machines/template/' . $fragment);
            $factory = new TemplateFactory($this);
            //TODO Add a chain to register template roots in
            yield from $factory->create($template)($data->data);
        });

        parent::__construct($this->helpers);
    }

    public function registerHelper(string $name, callable $callback): self
    {
        $this->helpers[$name] = $callback;

        return $this;
    }
}
