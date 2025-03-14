<?php

/** @noinspection PhpVariableIsUsedOnlyInClosureInspection */

declare(strict_types=1);

namespace Noem\State\Chains;

use Noem\State\Chains\Params;
use Noem\State\Connection;
use Noem\State\Middleware\Chain;
use Noem\State\Middleware\Mesh;
use Noem\State\Record;
use Noem\State\Region;
use SplObjectStorage;

/**
 * @template-extends Chain<Region,Mesh>
 */
class Meta extends Chain
{
    /**
     * @var list<Record>
     */
    private array $records = [];

    public function __construct(ConnectedRegions $connectedRegions)
    {
        /**
         * @var SplObjectStorage<Region,Mesh> $metaData
         */
        $metaData = new SplObjectStorage();

        parent::__construct(function (Region $region) use ($metaData): Mesh {
            if ($metaData->contains($region)) {
                return $metaData->offsetGet($region);
            }
            $validRecords = array_filter(
                $this->records,
                function ($record) use ($region) {
                    return !($record->hasFlag(Record::DYNAMIC) && !$record->isActive())
                        && $record->origin === $region;
                }
            );
            if (count($validRecords) === 0) {
                $mesh = new Mesh();
                $metaData->attach($region, $mesh);

                return $mesh;
            }
            $mesh = new Mesh(reset($validRecords)->data);
            if (count($validRecords) >= 1) {
                next($validRecords);
                foreach ($validRecords as $record) {
                    $mesh->extend($record->data);
                }
            }

            $metaData->attach($region, $mesh);

            return $mesh;
        });
        /**
         * Return the correct shared Mesh for a set of connections
         */
        $this->link(
            function (Region $region, callable $next, callable $first) use ($metaData, $connectedRegions): Mesh {
                $connectionParams = new Params\Connection(
                    $region,
                    false, // fetch a parent if exists
                    Connection::RECEIVE_META
                );
                $regions = $connectedRegions->call($connectionParams);
                if (count($regions) === 0) {
                    /**
                     * This Region has no parents.
                     * Allow the creation of a new Mesh
                     */
                    return $next($region);
                }
                $parentRegion = reset($regions);
                /**
                 * If this Region is connected to receive Meta
                 * and either child or parent has not been queried yet,
                 * we connect the Mesh for the current Region
                 * to the Mesh of the parent Region
                 */
                if (!$metaData->contains($parentRegion) || !$metaData->contains($region)) {
                    $parent = $first($parentRegion);
                    $child = $next($region);
                    $parent->extend($child);
                }

                return $metaData[$parentRegion];
            }
        );
    }

    public function addRecord(Record $record): self
    {
        $this->records[] = $record;

        return $this;
    }
}
