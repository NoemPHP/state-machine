<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader;

use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

readonly class ConvertYaml
{
    public function fromString(string $yaml, array $helpers): array
    {
        $array = Yaml::parse(
            $yaml,
            Yaml::PARSE_CUSTOM_TAGS
        );
        $this->processArray($array, $helpers);

        return $array;
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
