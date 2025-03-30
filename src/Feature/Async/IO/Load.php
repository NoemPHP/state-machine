<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async\IO;

class Load
{

    public function __construct(private string $filePath)
    {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(): \Generator
    {
        $resource = fopen($this->filePath, 'r');
        if ($resource === false) {
            throw new \Exception('Failed to open file');
        }
        yield from new StreamHandler($resource)();
    }
}
