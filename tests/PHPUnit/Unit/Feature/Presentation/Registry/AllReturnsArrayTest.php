<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.all() returns array of all registered RegionPresentations
 * Intent: Enables complete presentation enumeration before predicate filtering
 * Criticality: contract
 */
final class AllReturnsArrayTest extends TestCase
{
    public function testAllReturnsArrayOfPresentations(): void
    {
        $registry = new PresentationRegistry();
        $registry->setSchemas([
            'key1' => ['type' => 'string'],
            'key2' => ['type' => 'integer'],
        ]);

        $presentation1 = new RegionPresentation('key1', 'Label 1', 'Intent 1');
        $presentation2 = new RegionPresentation('key2', 'Label 2', 'Intent 2');

        $registry->register($presentation1);
        $registry->register($presentation2);

        $all = $registry->all();

        $this->assertIsArray($all);
        $this->assertCount(2, $all);
        $this->assertSame($presentation1, $all['key1']);
        $this->assertSame($presentation2, $all['key2']);
    }

    public function testAllReturnsEmptyArrayWhenNoRegistrations(): void
    {
        $registry = new PresentationRegistry();

        $all = $registry->all();

        $this->assertIsArray($all);
        $this->assertEmpty($all);
    }
}
