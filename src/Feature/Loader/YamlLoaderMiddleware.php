<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Context\LoaderContext;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\RegionBuilder;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

readonly class YamlLoaderMiddleware
{

    public function __construct(private mixed $source, private array $helpers)
    {
    }

    public function __invoke(LoaderContext $context, callable $next, callable $first): RegionBuilder
    {
        $data = &$context->data;
        /**
         * If we are already seeing an array, postprocess any TaggedValue entries
         */
        if (is_array($data)) {
            $this->processArray($data);

            return $next($context);
        }
        if (!is_string($this->source)) {
            return $next($context);
        }
        /**
         * If the context is a valid file path, load the contents
         */
        $newContext = is_readable($this->source)
            ? file_get_contents($this->source)
            : $this->source;
        $array = Yaml::parse(
            $newContext,
            Yaml::PARSE_CUSTOM_TAGS
        );
        $context->data = $array;

        return $first($context);
    }

    /**
     * Recursively processes an array to resolve TaggedValue instances.
     *
     * @param array $array The array to process.
     */
    private function processArray(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->processArray($value);
            } elseif ($value instanceof TaggedValue) {
                $value = $this->resolveHelper($value->getTag(), $value->getValue());
            }
        }
    }

    /**
     * Resolves a helper function based on the given name and content.
     *
     * @param string $name The name of the helper function to resolve.
     * @param string $content The content to be passed to the helper function.
     *
     * @return mixed The result of the helper function when found, or throws an exception if the helper is undefined.
     *
     * @throws \RuntimeException
     */
    private function resolveHelper(string $name, string $content): mixed
    {
        if (isset($this->helpers[$name])) {
            return $this->helpers[$name]($content);
        }

        throw new \RuntimeException("Undefined helper '{$name}'");
    }
}
