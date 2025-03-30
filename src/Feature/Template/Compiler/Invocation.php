<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template\Compiler;

class Invocation
{

    public function __construct(
        public readonly array|\ArrayAccess $data,
        public readonly array|\ArrayAccess $args,
        public readonly array|\ArrayAccess $hash,
        public readonly bool $isBlock = false,
        private(set) string $buffer = ''
    ) {
    }

    public function withData(array|\ArrayAccess $newData): self
    {
        return new self($newData, $this->args, $this->hash, $this->isBlock, $this->buffer);
    }

    public function withArgs(array|\ArrayAccess $newArgs): self
    {
        return new self($this->data, $newArgs, $this->hash, $this->isBlock, $this->buffer);
    }

    public function withHash(array|\ArrayAccess $newHash): self
    {
        return new self($this->data, $this->args, $newHash, $this->isBlock, $this->buffer);
    }

    public function withBlockFlag(bool $status): self
    {
        return new self($this->data, $this->args, $this->hash, $status, $this->buffer);
    }

    public function append(string $text): self
    {
        $this->buffer .= $text;

        return $this;
    }
}
