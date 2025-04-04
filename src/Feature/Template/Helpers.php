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
            if (!isset($data->data[$element])) {
                yield '';

                return;
            }
            $innerData = $data->data[$element];
            foreach ($innerData as $thing) {
                $newData = $data->setData(['this' => $thing]);
                yield from $data->blockContent($newData);
            }
            yield from $next($data);
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
            $template = '';
            //TODO Add a chain to register template roots in
            $dir = getcwd();
            $maybeFilename = $dir.'/machines/template/'.$fragment;

            if (is_readable($maybeFilename)) {
                $template = file_get_contents($maybeFilename);
            } elseif (
                isset($data->data[$fragment])
                && is_readable($data->data[$fragment])
            ) {
                $template = file_get_contents($data->data[$fragment]);
            }
            $factory = new TemplateFactory($this);
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
