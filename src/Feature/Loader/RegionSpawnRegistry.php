<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader;

class RegionSpawnRegistry
{
    private(set) array $records = [];

    public function addRecord(RegionSpawnRecord $record)
    {
        $this->records[] = $record;
    }
}
