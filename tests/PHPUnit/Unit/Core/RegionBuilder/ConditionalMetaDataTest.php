<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: setMetaData supports optional predicate for conditional metadata
 */
#[Group('region-builder')]
#[Group('metadata-management')]
class ConditionalMetaDataTest extends TestCase
{
    public function testSetMetaDataAcceptsOptionalPredicate(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $predicate = fn(): bool => true;

        $result = $builder->setMetaData(
            ['conditional' => 'data'],
            ContextMetaType::get(),
            predicate: $predicate
        );

        $this->assertSame($builder, $result, 'setMetaData should return builder for chaining');

        $region = $builder->build();
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testPredicateCanBeUsedForConditionalMetadata(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing');

        $enabledCondition = fn(): bool => true;
        $disabledCondition = fn(): bool => false;

        $builder->setMetaData(
            ['enabled_feature' => 'value'],
            ContextMetaType::get(),
            predicate: $enabledCondition
        );

        $builder->setMetaData(
            ['disabled_feature' => 'value'],
            ContextMetaType::get(),
            predicate: $disabledCondition
        );

        $region = $builder->build();
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testPredicateWithFlagsAndMetadata(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $condition = fn(): bool => true;
        $flags = 1 << 3;

        $builder->setMetaData(
            ['complex' => 'metadata'],
            ContextMetaType::get(),
            $flags,
            $condition
        );
        
        $region = $builder->build();
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }
}
