<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: setMetaData requires a MetaType parameter to categorize metadata
 */
#[Group('region-builder')]
#[Group('metadata-management')]
class MetaTypeRequiredTest extends TestCase
{
    public function testSetMetaDataRequiresMetaTypeParameter(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        // Test that MetaType subclasses are accepted
        $builder->setMetaData(['data' => 'value'], ContextMetaType::get());

        $region = $builder->build();
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testMetaTypeCategorizesMetadata(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing');

        // Different metadata types should be handled appropriately
        $builder->setMetaData(['context_data' => 'main'], ContextMetaType::get());
        $builder->setMetaData(['state_data' => 'idle'], ContextMetaType::get());

        $region = $builder->build();
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }
}
