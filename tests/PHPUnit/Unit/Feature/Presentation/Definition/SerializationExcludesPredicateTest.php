<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Definition;

use Noem\State\Feature\Presentation\RegionPresentation;
use Noem\State\Region;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: RegionPresentation.jsonSerialize() excludes predicate property
 * Intent: Prevents serialization of non-serializable callables, keeping output clean for transmission
 * Criticality: contract
 */
final class SerializationExcludesPredicateTest extends TestCase
{
    public function testJsonSerializeExcludesPredicate(): void
    {
        $predicate = fn(Region $region): bool => true;

        $presentation = new RegionPresentation(
            key: 'key',
            label: 'Label',
            intent: 'Intent',
            predicate: $predicate
        );

        $serialized = $presentation->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertArrayNotHasKey('predicate', $serialized);
    }
}
