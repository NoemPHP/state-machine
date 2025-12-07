<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: setMetaData supports optional flags parameter for configuration
 */
#[Group('region-builder')]
#[Group('metadata-management')]
class MetaDataFlagsTest extends TestCase
{
    public function testSetMetaDataAcceptsOptionalFlags(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        // Test with default flags (0)
        $builder->setMetaData(['key' => 'value'], ContextMetaType::get());

        // Test with custom flags
        $builder->setMetaData(['key2' => 'value2'], ContextMetaType::get(), flags: 1);
        $builder->setMetaData(['key3' => 'value3'], ContextMetaType::get(), flags: 255);

        $region = $builder->build();
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testFlagsCanBeUsedForConfiguration(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing');

        // Flags can be used to mark metadata with different behaviors
        $readOnlyFlag = 1 << 0;
        $cacheableFlag = 1 << 1;
        $persistentFlag = 1 << 2;

        $builder->setMetaData(['readonly' => true], ContextMetaType::get(), $readOnlyFlag);
        $builder->setMetaData(['cached' => true], ContextMetaType::get(), $cacheableFlag);
        $builder->setMetaData(['persist' => true], ContextMetaType::get(), $persistentFlag);
        $builder->setMetaData(['combined' => true], ContextMetaType::get(), $readOnlyFlag | $cacheableFlag);

        $region = $builder->build();
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }
}
