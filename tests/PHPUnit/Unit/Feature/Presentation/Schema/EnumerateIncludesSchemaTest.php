<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Schema;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: enumerate-presentations ability includes schema in response for each presentation
 * Intent: Bundles schema with presentation metadata enabling external validation
 * Criticality: contract
 */
final class EnumerateIncludesSchemaTest extends TestCase
{
    public function testEnumerateIncludesSchemaDefinition(): void
    {
        $registry = new PresentationRegistry();

        $schemas = [
            'testKey' => [
                'type' => 'string',
                'description' => 'Test field',
                'minLength' => 1,
            ],
        ];

        $registry->setSchemas($schemas);

        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent'
        );

        $registry->register($presentation);

        // Verify schema is retrievable for enumeration
        $schema = $registry->getSchema('testKey');

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('type', $schema);
        $this->assertArrayHasKey('description', $schema);
        $this->assertSame('string', $schema['type']);
        $this->assertSame('Test field', $schema['description']);
    }

    public function testSchemaIncludedForEachPresentation(): void
    {
        $registry = new PresentationRegistry();

        $schemas = [
            'field1' => ['type' => 'string'],
            'field2' => ['type' => 'integer'],
            'field3' => ['type' => 'boolean'],
        ];

        $registry->setSchemas($schemas);

        $presentation1 = new RegionPresentation('field1', 'Label 1', 'Intent 1');
        $presentation2 = new RegionPresentation('field2', 'Label 2', 'Intent 2');
        $presentation3 = new RegionPresentation('field3', 'Label 3', 'Intent 3');

        $registry->register($presentation1);
        $registry->register($presentation2);
        $registry->register($presentation3);

        // Each presentation should have its schema available
        $this->assertSame(['type' => 'string'], $registry->getSchema('field1'));
        $this->assertSame(['type' => 'integer'], $registry->getSchema('field2'));
        $this->assertSame(['type' => 'boolean'], $registry->getSchema('field3'));
    }
}
