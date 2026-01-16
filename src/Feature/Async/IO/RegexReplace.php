<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async\IO;

/**
 * Regex-based file text replacement with capture group support.
 *
 * Loads full file into memory (required for regex), performs replacement,
 * and writes back using atomic rename pattern for safety.
 * Yields between operations for cooperative multitasking.
 */
class RegexReplace
{
    public function __construct(
        private string $filePath,
        private string $pattern,
        private string $replacement,
        private int $limit = -1
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(): \Generator
    {
        // Read entire file
        $content = file_get_contents($this->filePath);
        if ($content === false) {
            throw new \Exception("Failed to read file: {$this->filePath}");
        }

        yield; // Cooperative yield after reading

        // Perform regex replacement
        $replacementCount = 0;
        /** @psalm-suppress ArgumentTypeCoercion - $pattern is validated at runtime by preg_replace */
        $newContent = preg_replace($this->pattern, $this->replacement, $content, $this->limit, $replacementCount);

        // Check for preg_replace errors
        if ($newContent === null) {
            $error = preg_last_error_msg();
            throw new \Exception("preg_replace failed: {$error}");
        }

        yield; // Cooperative yield after replacement

        // Write to temp file for atomic rename
        $tempPath = $this->filePath . '.tmp.' . uniqid();
        $written = file_put_contents($tempPath, $newContent);
        if ($written === false) {
            throw new \Exception("Failed to write temp file: {$tempPath}");
        }

        yield; // Cooperative yield after writing

        // Atomic rename
        if (!rename($tempPath, $this->filePath)) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            throw new \Exception("Failed to rename temp file to original: {$tempPath} -> {$this->filePath}");
        }

        return $replacementCount;
    }
}
