<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can set metadata using setMetaData with array or ArrayAccess
 */
#[Group('region-builder')]
#[Group('metadata-management')]
class SetMetaDataTest extends TestCase
{
    public function testSetMetaDataAcceptsArray(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $metadata = ['key' => 'value', 'number' => 42];

        $result = $builder->setMetaData($metadata, ContextMetaType::get());

        $this->assertSame($builder, $result, 'setMetaData should return builder for chaining');

        $region = $builder->build();
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testSetMetaDataAcceptsArrayAccess(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $metadata = new \ArrayObject(['key' => 'value', 'config' => true]);

        $result = $builder->setMetaData($metadata, ContextMetaType::get());

        $this->assertSame($builder, $result, 'setMetaData should return builder for chaining');

        $region = $builder->build();
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testSetMetaDataCanBeCalledMultipleTimes(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $builder->setMetaData(['first' => 1], ContextMetaType::get())
                ->setMetaData(['second' => 2], ContextMetaType::get())
                ->setMetaData(['third' => 3], ContextMetaType::get());
        
        $region = $builder->build();
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }
}
