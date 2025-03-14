<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use PHPUnit\Framework\TestCase;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\Meta;
use Noem\State\Connection;
use Noem\State\Region;
use Noem\State\Record;

class ConnectedRegionsTest extends TestCase
{

    public function testConnectedRegionsReceiveSharedMetadataMesh()
    {
        // Create regions
        $region1 = \Mockery::mock(Region::class);
        $region2 = \Mockery::mock(Region::class);

        // Create connections
        $connection = new Connection(
            $region1,
             $region2,
            Connection::RECEIVE_META
        );

        // Create records with metadata
        $record1 = new Record($region1, ['a' => 'foo']);
        $record2 = new Record($region2, ['b' => 'bar']);

        // Initialize ConnectedRegions and Meta chains
        $connectedRegions = new ConnectedRegions()
            ->addConnection($connection);

        $metaChain = new Meta($connectedRegions)
            ->addRecord($record1)
            ->addRecord($record2);

        // Get metadata mesh for region1
        $meshForRegion1 = $metaChain->call($region1);
        $this->assertArrayHasKey('a', $meshForRegion1);
        $this->assertArrayNotHasKey('b', $meshForRegion1);
        // Get metadata mesh for region2
        $meshForRegion2 = $metaChain->call($region2);
        $this->assertArrayHasKey('a', $meshForRegion2);
        $this->assertArrayHasKey('b', $meshForRegion2);
    }
}
