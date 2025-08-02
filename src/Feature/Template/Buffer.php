<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template;

class Buffer implements \Stringable
{

    private array $chunks = [];

    public function add(string $chunk): void
    {
        $this->chunks[] = $chunk;
    }

    public function __toString(): string
    {
        return implode('', $this->chunks);
    }
}
