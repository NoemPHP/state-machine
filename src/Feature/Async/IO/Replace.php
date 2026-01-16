<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async\IO;

/**
 * Streaming literal string replacement for huge files with constant memory usage.
 *
 * Uses temp file + atomic rename pattern for safety.
 * Handles matches spanning chunk boundaries via suffix/prefix buffering.
 * Memory: O(chunk + search_length) - constant regardless of file size.
 */
class Replace
{
    public function __construct(
        private string $filePath,
        private string $search,
        private string $replacement,
        private int $limit = -1,
        private int $chunkSize = 8192
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(): \Generator
    {
        // Empty search string is a no-op
        if ($this->search === '') {
            yield from [];
            return 0;
        }

        $searchLen = strlen($this->search);

        // Open source file for reading
        $sourceHandle = fopen($this->filePath, 'rb');
        if ($sourceHandle === false) {
            throw new \Exception("Failed to open file for reading: {$this->filePath}");
        }

        // Create temp file for atomic write
        $tempPath = $this->filePath . '.tmp.' . uniqid();
        $tempHandle = fopen($tempPath, 'wb');
        if ($tempHandle === false) {
            fclose($sourceHandle);
            throw new \Exception("Failed to create temp file: {$tempPath}");
        }

        yield; // Cooperative yield after opening files

        $replacementCount = 0;
        $buffer = '';
        $limitReached = false;

        try {
            while (!feof($sourceHandle)) {
                $chunk = fread($sourceHandle, $this->chunkSize);
                if ($chunk === false) {
                    throw new \Exception("Failed to read chunk from: {$this->filePath}");
                }

                $buffer .= $chunk;

                yield; // Cooperative yield after reading chunk

                // If limit reached, just write through
                if ($limitReached) {
                    if (fwrite($tempHandle, $buffer) === false) {
                        throw new \Exception("Failed to write to temp file: {$tempPath}");
                    }
                    $buffer = '';
                    continue;
                }

                // Process buffer, keeping potential partial match at end
                while (true) {
                    $pos = strpos($buffer, $this->search);

                    if ($pos !== false) {
                        // Found a match - write everything before it + replacement
                        if ($pos > 0) {
                            if (fwrite($tempHandle, substr($buffer, 0, $pos)) === false) {
                                throw new \Exception("Failed to write to temp file: {$tempPath}");
                            }
                        }
                        if (fwrite($tempHandle, $this->replacement) === false) {
                            throw new \Exception("Failed to write to temp file: {$tempPath}");
                        }

                        $buffer = substr($buffer, $pos + $searchLen);
                        $replacementCount++;

                        // Check limit
                        if ($this->limit !== -1 && $replacementCount >= $this->limit) {
                            $limitReached = true;
                            // Write remaining buffer
                            if ($buffer !== '' && fwrite($tempHandle, $buffer) === false) {
                                throw new \Exception("Failed to write to temp file: {$tempPath}");
                            }
                            $buffer = '';
                            break;
                        }
                    } else {
                        // No match found - keep last (searchLen-1) bytes for boundary handling
                        $safeLen = strlen($buffer) - ($searchLen - 1);
                        if ($safeLen > 0) {
                            if (fwrite($tempHandle, substr($buffer, 0, $safeLen)) === false) {
                                throw new \Exception("Failed to write to temp file: {$tempPath}");
                            }
                            $buffer = substr($buffer, $safeLen);
                        }
                        break;
                    }
                }

                yield; // Cooperative yield after processing chunk
            }

            // Write any remaining buffer
            if ($buffer !== '') {
                if (fwrite($tempHandle, $buffer) === false) {
                    throw new \Exception("Failed to write to temp file: {$tempPath}");
                }
            }

            fclose($sourceHandle);
            fclose($tempHandle);

            yield; // Cooperative yield before rename

            // Atomic rename
            if (!rename($tempPath, $this->filePath)) {
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
                throw new \Exception("Failed to rename temp file to original: {$tempPath} -> {$this->filePath}");
            }
        } catch (\Exception $e) {
            // Cleanup on error
            if (is_resource($sourceHandle)) {
                fclose($sourceHandle);
            }
            if (is_resource($tempHandle)) {
                fclose($tempHandle);
            }
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            throw $e;
        }

        return $replacementCount;
    }
}
