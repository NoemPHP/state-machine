<?php

declare(strict_types=1);

namespace ProcessWrapper;

use Noem\State\Feature\Message\Message;
use Noem\State\Feature\Message\StandardMessage;

/**
 * IFS-delimited stream parser with JSON detection
 *
 * Buffers input until field separator (default: newline), then:
 * - If content looks like JSON and parses successfully: yield Message
 * - Otherwise: yield raw string
 *
 * Supports passthrough mode where data is yielded immediately without buffering.
 */
class StreamParser
{
    private string $buffer = '';
    private string $delimiter;
    private bool $passthrough;

    public function __construct(
        ?string $delimiter = null,
        bool $passthrough = false
    ) {
        // Read from IFS environment variable, default to newline
        $this->delimiter = $delimiter ?? $this->getIfsDelimiter();
        $this->passthrough = $passthrough;
    }

    private function getIfsDelimiter(): string
    {
        $ifs = getenv('IFS');
        if ($ifs === false || $ifs === '') {
            return "\n";
        }
        // Use first character of IFS as delimiter
        return $ifs[0];
    }

    /**
     * Feed a chunk of data, yield parsed segments
     *
     * @return \Generator<Message|string>
     */
    public function feed(string $chunk): \Generator
    {
        if ($this->passthrough) {
            yield $chunk;
            return;
        }

        $this->buffer .= $chunk;

        // Process all complete segments
        while (($delimPos = strpos($this->buffer, $this->delimiter)) !== false) {
            $segment = substr($this->buffer, 0, $delimPos);
            $this->buffer = substr($this->buffer, $delimPos + strlen($this->delimiter));

            if ($segment === '') {
                continue;
            }

            yield $this->parseSegment($segment);
        }
    }

    /**
     * Flush any remaining buffer content
     *
     * @return \Generator<Message|string>
     */
    public function flush(): \Generator
    {
        if ($this->buffer !== '') {
            yield $this->parseSegment($this->buffer);
            $this->buffer = '';
        }
    }

    /**
     * Parse a segment, attempting JSON hydration
     */
    private function parseSegment(string $segment): Message|string
    {
        $trimmed = trim($segment);
        $firstChar = $trimmed[0] ?? '';

        // Only attempt JSON parse if it looks like JSON
        if ($firstChar === '{' || $firstChar === '[') {
            $decoded = json_decode($trimmed, associative: true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->hydrate($decoded);
            }
        }

        // Return raw segment
        return $segment;
    }

    /**
     * Hydrate JSON data into a Message
     */
    private function hydrate(array $data): Message
    {
        // Use Message::fromJson which handles type resolution and protected fromData
        return Message::fromJson($data);
    }

    /**
     * Get remaining buffer content (for debugging)
     */
    public function getBuffer(): string
    {
        return $this->buffer;
    }

    /**
     * Clear the buffer
     */
    public function reset(): void
    {
        $this->buffer = '';
    }
}
