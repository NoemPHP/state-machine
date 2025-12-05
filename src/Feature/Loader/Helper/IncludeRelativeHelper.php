<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\Helper;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Includes\Chains\LoadFile;
use Noem\State\Feature\Includes\LoadFileParams;
use Noem\State\Feature\Loader\ConvertYaml;

/**
 * YAML helper for !includeRelative tag that loads files relative to current file.
 *
 * Enables portable YAML modules by resolving paths relative to the file being parsed.
 * First invocation uses basePath (no current file context yet).
 * Subsequent invocations resolve relative to the current file's directory.
 */
class IncludeRelativeHelper
{
    use DepthTracker;

    public function __construct(
        private readonly LoadFile       $loadFile,
        private readonly BuildParams    $buildParams,
        private readonly ConvertYaml    $convertYaml,
        private readonly LoadFileParams $currentParams,
        private ?string                 $currentFile = null,
    )
    {
    }

    public function __invoke(string $path): mixed
    {
        // Resolve path relative to current file (or basePath for first invocation)
        $resolvedPath = $this->resolvePath($path);
        $this->currentFile = $resolvedPath;
        // Track recursion depth for this include operation
        return $this->trackDepth($resolvedPath, function () use ($resolvedPath) {
            // Create params for this specific file
            $newParams = new LoadFileParams($resolvedPath, $this->buildParams);

            // Load file content using LoadFile chain
            $content = $this->loadFile->call($newParams);

            // Try to parse as YAML with ConvertYaml service for recursive includes
            // If parsing fails or returns scalar, return content as-is
            try {
                $parsed = yaml_parse($content);
                if (is_array($parsed)) {
                    // It's a YAML structure, process with configured ConvertYaml instance
                    return $this->convertYaml->fromString($content);
                }
                // Scalar value, return as-is
                return $parsed ?? $content;
            } catch (\RuntimeException $e) {
                // Re-throw RuntimeExceptions (like depth limit errors) - don't swallow them
                throw $e;
            } catch (\Throwable $e) {
                // Not valid YAML or parsing failed, return raw content
                return $content;
            }
        });
        $this->currentFile = null;
    }

    private function resolvePath(string $path): string
    {
        // Absolute paths are used as-is
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        // If we have a current file, resolve relative to its directory
        if ($this->currentFile !== null) {
            $currentDir = dirname($this->currentFile);
            return $currentDir . '/' . ltrim($path, '/');
        }

        // First invocation: use basePath (no current file context yet)
        $basePath = $this->buildParams->getPath('loader.array.includes.basePath')
            ?? getcwd();

        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }

    private function isAbsolutePath(string $path): bool
    {
        // Unix absolute path
        if ($path[0] === '/') {
            return true;
        }

        // Windows absolute path (e.g., C:\path or C:/path)
        if (strlen($path) > 1 && $path[1] === ':') {
            return true;
        }

        return false;
    }

    /**
     * Create updated helpers with new current file context for nested relative includes.
     *
     * @param string $newCurrentFile The resolved path of the file being included
     * @param LoadFileParams $newParams Updated params
     * @return array Helper overrides for nested parsing
     */
    private function createUpdatedHelpers(
        string         $newCurrentFile,
        LoadFileParams $newParams
    ): array
    {
        // Create updated helper instances with new current file context
        // These will override the base helpers from ConvertYaml for this nested parse
        // Pass current depth to new instances so they continue tracking from this level
        $includeHelper = new IncludeHelper(
            $this->loadFile,
            $this->buildParams,
            $this->convertYaml,
            $newParams
        );
        $includeHelper->initializeDepth($this->getCurrentDepth());

        $includeRelativeHelper = new self(
            $this->loadFile,
            $this->buildParams,
            $this->convertYaml,
            $newParams,
            $newCurrentFile
        );
        $includeRelativeHelper->initializeDepth($this->getCurrentDepth());

        return [
            'include' => $includeHelper,
            'includeRelative' => $includeRelativeHelper,
        ];
    }
}
