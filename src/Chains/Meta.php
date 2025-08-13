<?php

/** @noinspection PhpVariableIsUsedOnlyInClosureInspection */

declare(strict_types=1);

namespace Noem\State\Chains;

use Noem\State\Chains\Params;
use Noem\State\Connection;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\MetaData;
use Noem\State\Middleware\Chain;
use Noem\State\Middleware\Mesh;
use Noem\State\Record;
use Noem\State\Region;
use Override;
use SplObjectStorage;

/**
 * @template-extends Chain<Params\Meta,Mesh>
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
         * @var SplObjectStorage<Region,MetaData> $metaData
         */
        $metaData = new SplObjectStorage();

        parent::__construct(function (Params\Meta $metaParams) use ($metaData): Mesh {
            $regionId = spl_object_id($metaParams->region);
            if (!$metaData->contains($metaParams->region)) {
                $metaData->attach($metaParams->region, new MetaData());
            }
            $metaType = (string)$metaParams->type;
            $meta = $metaData->offsetGet($metaParams->region);
            $metaId = spl_object_id($meta);
            if ($meta->offsetExists($metaType)) {
                return $meta->offsetGet($metaType);
            }
            $validRecords = array_filter(
                $this->records,
                function ($record) use ($metaParams) {
                    return !($record->hasFlag(Record::DYNAMIC) && !$record->isActive())
                        && $record->origin === $metaParams->region;
                }
            );
            if (count($validRecords) === 0) {
                $mesh = new Mesh();
                $meta[$metaType] = $mesh;

                return $mesh;
            }
            $mesh = new Mesh(reset($validRecords)->data);
            if (count($validRecords) >= 1) {
                next($validRecords);
                foreach ($validRecords as $record) {
                    $mesh->extendWith($record->data);
                }
            }

            $meta[$metaType] = $mesh;

            return $mesh;
        });
        /**
         * Return the correct shared Mesh for a set of connections
         */
        $this->link(
            function (
                Params\Meta $metaParams,
                callable $next,
                callable $first
            ) use (
                $metaData,
                $connectedRegions
            ): Mesh {
                $connectionParams = new Params\Connection(
                    $metaParams->region,
                    false, // fetch a parent if exists
                    Connection::RECEIVE_META
                );
                $regions = $connectedRegions->call($connectionParams);
                if (count($regions) === 0) {
                    /**
                     * This Region has no parents.
                     * Allow the creation of a new Mesh
                     */
                    return $next($metaParams);
                }
                $parentRegion = reset($regions);
                /**
                 * If this Region is connected to receive Meta
                 * and either child or parent has not been queried yet,
                 * we connect the Mesh for the current Region
                 * to the Mesh of the parent Region
                 * TODO It must be possible to unhook temporary (spawned) MRegions
                 */
                if (!$metaData->contains($parentRegion) || !$metaData->contains($metaParams->region)) {
                    $parentMetaParams = new Params\Meta($parentRegion, ContextMetaType::get());
                    $parent = $first($parentMetaParams);
                    assert($parent instanceof Mesh);
                    $child = $next($metaParams);
                    $parent->extendwith($child);
                }

                return $metaData[$parentRegion][(string)$metaParams->type];
            }
        );
    }

    #[Override] public function call(mixed $context): Mesh
    {
        assert($context instanceof Params\Meta);

        return parent::call($context);
    }

    public function addRecord(Record $record): self
    {
        $this->records[] = $record;

        return $this;
    }
}
