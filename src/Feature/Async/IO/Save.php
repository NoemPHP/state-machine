<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async\IO;

class Save
{
    public function __construct(
        private string $filePath,
        private string|\Generator $data,
        private string $mode = 'w',
        private int $chunkSize = 8192
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(): \Generator
    {
        $resource = fopen($this->filePath, $this->mode);
        if ($resource === false) {
            throw new \Exception('Failed to open file');
        }

        try {
            $totalBytesWritten = 0;

            if ($this->data instanceof \Generator) {
                // Generator mode: consume generator values and write them
                // First, ensure generator is started
                if (!$this->data->valid()) {
                    $this->data->rewind();
                }

                while ($this->data->valid()) {
                    $value = $this->data->current();

                    $bytesWritten = fwrite($resource, (string)$value);
                    if ($bytesWritten === false) {
                        throw new \Exception('Failed to write to file');
                    }
                    $totalBytesWritten += $bytesWritten;

                    $this->data->next();
                    yield;
                }
            } else {
                // String mode: write in chunks
                $dataLength = strlen($this->data);
                $offset = 0;

                while ($offset < $dataLength) {
                    $chunk = substr($this->data, $offset, $this->chunkSize);
                    $bytesWritten = fwrite($resource, $chunk);

                    if ($bytesWritten === false) {
                        throw new \Exception('Failed to write to file');
                    }

                    $totalBytesWritten += $bytesWritten;
                    $offset += $this->chunkSize;

                    yield;
                }
            }

            return $totalBytesWritten;
        } finally {
            fclose($resource);
        }
    }
}
