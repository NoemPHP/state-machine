<?php

namespace Noem\State;

final class Connection
{

    public const RECEIVE_ACTIONS = 1; // 001 in octal
    public const RECEIVE_EVENTS = 2; // 002 in octal
    public const RECEIVE_META = 4; // 004 in octal
    public const DYNAMIC = 8; // 004 in octal

    private int $flags;

    private $predicate;

    public function __construct(
        public readonly Region $local,
        public readonly Region $remote,
        int $flags = 0,
        ?callable $predicate = null
    ) {
        $this->flags = $flags;
        $this->predicate = $predicate;
    }

    public function setFlag(int $flag): self
    {
        $this->flags |= $flag;

        return $this;
    }

    public function clearFlag(int $flag): self
    {
        $this->flags &= ~$flag;

        return $this;
    }

    public function hasFlag(int $flag): bool
    {
        return ($this->flags & $flag) === $flag;
    }

    public function setPredicate(callable $regionFilter): self
    {
        $this->predicate = $regionFilter;

        return $this;
    }

    public function isActive(): bool
    {
        if (!$this->predicate) {
            return false;
        }

        return ($this->predicate)($this);
    }
}
