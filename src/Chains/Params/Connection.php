<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\Connection as Conn;
use Noem\State\Region;

class Connection
{
    public function __construct(
        public readonly Region $region,
        /**
         * true: $region is the local region of this connection.
         * false: $region is the remote region of this connection.
         */
        public readonly ?bool $searchMode = true,
        public readonly int $flags = Conn::DYNAMIC,
    ) {
    }

    public function hasFlag(int $flag): bool
    {
        return ($this->flags & $flag) === $flag;
    }
}
