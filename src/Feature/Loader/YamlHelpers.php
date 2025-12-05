<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader;

/**
 * Simple registry for YAML helper functions.
 *
 * Allows features to register custom YAML tag handlers that can be used
 * during YAML parsing by ConvertYaml.
 */
class YamlHelpers
{
    /**
     * @var array<string, callable>
     */
    private array $helpers = [];

    /**
     * Register a helper function by name.
     *
     * @param string $name The YAML tag name (without ! prefix)
     * @param callable $helper The helper function
     * @throws \RuntimeException If helper name already registered
     */
    public function register(string $name, callable $helper): void
    {
        if (isset($this->helpers[$name])) {
            throw new \RuntimeException("Helper \"{$name}\" is already registered");
        }

        $this->helpers[$name] = $helper;
    }

    /**
     * Get all registered helpers.
     *
     * @return array<string, callable>
     */
    public function getHelpers(): array
    {
        return $this->helpers;
    }
}
