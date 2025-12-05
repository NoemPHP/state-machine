<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\Helper;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Includes\Chains\LoadFile;
use Noem\State\Feature\Includes\LoadFileParams;
use Noem\State\Feature\Loader\ConvertYaml;

/**
 * YAML helper for !include tag that loads files relative to configured basePath.
 *
 * Provides fixed working directory resolution for centralized configs.
 * Paths are resolved relative to loader.array.includes.basePath (or cwd if not configured).
 */
class IncludeHelper
{
    use DepthTracker;

    public function __construct(
        private readonly LoadFile       $loadFile,
        private readonly BuildParams    $buildParams,
        private readonly ConvertYaml    $convertYaml,
        private readonly LoadFileParams $currentParams,
    )
    {
    }

    public function __invoke(string $path): mixed
    {
        // Resolve path relative to basePath
        $resolvedPath = $this->resolvePath($path);

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
    }

    private function resolvePath(string $path): string
    {
        // Absolute paths are used as-is
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        // Get basePath from configuration (default to cwd)
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
}
