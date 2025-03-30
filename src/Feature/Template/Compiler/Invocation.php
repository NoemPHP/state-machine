<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template\Compiler;

class Invocation
{
    public function __construct(
        public array|\ArrayAccess $data,
        public array|\ArrayAccess $args,
        public array|\ArrayAccess $hash,
        public bool               $isBlock = false,
        public string $buffer = ''
    )
    {
        $foo=1;
    }

    public function setData(array|\ArrayAccess $newData): self
    {
        $this->data = $newData;
        return $this;
    }

    public function setArgs(array|\ArrayAccess $newArgs): self
    {
        $this->args = $newArgs;
        return $this;
    }

    public function setHash(array|\ArrayAccess $newHash): self
    {
        $this->hash = $newHash;
        return $this;
    }

    public function setBlockFlag(bool $status): self
    {
        $this->isBlock = $status;
        return $this;
    }

    public function append(string $text): self
    {
        $this->buffer .= $text;

        return $this;
    }

}
