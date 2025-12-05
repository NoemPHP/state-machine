<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader;

use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

readonly class ConvertYaml
{
    /**
     * @param YamlHelpers|null $yamlHelpers Optional registry for feature-registered helpers
     * @param array<string, callable> $constructorHelpers Optional helpers provided at construction time
     */
    public function __construct(
        private ?YamlHelpers $yamlHelpers = null,
        private array $constructorHelpers = []
    ) {
    }

    /**
     * Convert YAML string to array, processing custom tags with helpers.
     *
     * @param string $yaml The YAML string to parse
     * @param array $additionalHelpers Optional helpers to merge with registry helpers (take precedence)
     * @return array The parsed array
     */
    public function fromString(string $yaml, array $additionalHelpers = []): array
    {
        // Merge helpers: registry → constructor → additional (later takes precedence)
        $helpers = $this->yamlHelpers?->getHelpers() ?? [];
        $helpers = array_merge($helpers, $this->constructorHelpers, $additionalHelpers);

        $parsed = Yaml::parse(
            $yaml,
            Yaml::PARSE_CUSTOM_TAGS
        );

        // Handle top-level TaggedValue (e.g., entire YAML is "!include file")
        if ($parsed instanceof TaggedValue) {
            $parsed = $this->resolveHelper($parsed, $helpers);
        }

        // Ensure we have an array after processing top-level tags
        if (!is_array($parsed)) {
            throw new \RuntimeException('YAML must resolve to an array structure');
        }

        // Process the array recursively to handle any nested TaggedValues
        // This is needed even if we resolved a top-level tag, as the result
        // may contain nested tags
        $this->processArray($parsed, $helpers);

        return $parsed;
    }

    /**
     * Recursively processes an array to resolve TaggedValue instances.
     *
     * @param array $array The array to process.
     */
    private function processArray(array &$array, array $helpers): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->processArray($value, $helpers);
            } elseif ($value instanceof TaggedValue) {
                $value = $this->resolveHelper($value, $helpers);
            }
        }
    }

    /**
     * Resolves a helper function based on the provided TaggedValue and executes it.
     *
     * @param TaggedValue $helper The tagged value containing the helper name and its arguments.
     * @param array $helpers An array of available helpers.
     *
     * @return mixed The result of executing the resolved helper function.
     */
    private function resolveHelper(TaggedValue $helper, array $helpers): mixed
    {
        $name = $helper->getTag();
        if (isset($helpers[$name])) {
            return $helpers[$name]($helper->getValue());
        }

        throw new \RuntimeException("Undefined helper '{$name}'");
    }
}
