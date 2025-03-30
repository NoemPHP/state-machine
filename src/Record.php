<?php

namespace Noem\State;

use ArrayAccess;

final class Record
{

    /**
     * Whether to wipe the record upon transitions
     */
    public const TRANSIENT = 1; // 001 in octal
    /**
     * Whether to treat the metadata contained herein as classifications.
     * This may be used to query regions by category
     */
    public const TAXONOMIC = 2; // 002 in octal
    /**
     * Whether to allow external write access
     */
    public const READONLY = 4; // 004 in octal
    /**
     * Whether to gate read access behind a predicate function
     * This may be used to make certain data only appear in certain states of a region
     */
    public const DYNAMIC = 8; // 004 in octal

    private int $flags;

    /**
     * @var callable():bool
     */
    private $predicate;

    public function __construct(
        public readonly Region $origin,
        public array|ArrayAccess $data,
        public MetaType $type,
        int $flags = 0,
        ?callable $predicate = null
    ) {
        $this->flags = $flags;
        $this->predicate = $predicate ?? fn() => true;
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

