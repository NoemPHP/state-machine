<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

use Noem\State\Region;

class Resolvers
{

    /**
     * @var array
     * <string, ResolverRecord>
     */
    private array $resolverRecords = [];

    public function __construct()
    {
    }

    public function addResolver(ResolverRecord $record): void
    {
        $key = $this->generateKey($record->region, $record->key);
        $this->resolverRecords[$key] = $record;
    }

    public function hasResolver(Region $region, string $key): bool
    {
        $searchKey = $this->generateKey($region, $key);

        return isset($this->resolverRecords[$searchKey]);
    }

    public function getResolver(Region $region, string $key): ?ResolverRecord
    {
        $searchKey = $this->generateKey($region, $key);

        return $this->resolverRecords[$searchKey] ?? null;
    }

    /**
     * @param Region $region
     *
     * @return ResolverRecord[]
     */
    public function getResolversForRegion(Region $region): array
    {
        $resolvers = [];
        foreach ($this->resolverRecords as $record) {
            assert($record instanceof ResolverRecord);
            if ($record->region === $region) {
                $resolvers[$record->key] = $record;
            }
        }

        return $resolvers;
    }

    private function generateKey(Region $region, string $key): string
    {
        return spl_object_id($region).':'.$key;
    }
}
