<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template\Compiler;

use Noem\State\Middleware\Chain;

class Invocation
{
    public function __construct(
        public array|\ArrayAccess $data,
        public array|\ArrayAccess $args,
        public array|\ArrayAccess $hash,
        public TemplateFactory $templateFactory,
        public ?Chain $blockContent = null,
    ) {
        $foo = 1;
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

    public function isBlock(): bool
    {
        return $this->blockContent !== null;
    }

    public function setBlockContents(?Chain $content): self
    {
        $this->blockContent = $content;

        return $this;
    }

    public function getBuffer(): string
    {
        return $this->templateFactory->getBuffer();
    }

    public function blockContent(?Invocation $invocation = null): \Generator
    {
        if (!$this->isBlock()) {
            return function () {
                return yield '';
            };
        }

        return $this->blockContent->call($invocation ?? $this);
    }
}
