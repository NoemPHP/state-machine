<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async\IO;

class StreamHandler
{
    /**
     * @var resource
     */
    private $resource;

    public function __construct($resource)
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('Resource must be a resource');
        }
        $this->resource = $resource;
    }

    public function __invoke(): \Generator
    {
        do {
            $chunk = fread($this->resource, 1024);
            $metastatus = stream_get_meta_data($this->resource);
            if ($metastatus['timed_out']) {
                fclose($this->resource);
                throw new \Exception('Read timed out');
            }
            yield $chunk;
        } while (!feof($this->resource));
        fclose($this->resource);
    }
}
