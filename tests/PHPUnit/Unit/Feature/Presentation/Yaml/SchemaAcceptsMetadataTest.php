<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Yaml;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Schema validates 'metadata' as optional object in presentation definitions
 * Intent: Accepts format hints while remaining optional for minimal declarations
 * Criticality: contract
 */
final class SchemaAcceptsMetadataTest extends TestCase
{
    public function testMetadataIsOptional(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new ExtendedState(),
            new JsonSchemaFeature(),
            new AbilitiesFeature(),
            new PresentationFeature(),
            new RegionLoader()
        );

        $yamlContent = <<<'YAML'
context:
  schema:
    - name: testField
      type: string
      default: test
presentations:
  - key: testField
    label: Test Label
    intent: Test Intent
states:
  - name: initial
YAML;

        // Should not throw exception when metadata is omitted
        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);

        $this->assertNotNull($region);
    }

    public function testMetadataCanBeObject(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new ExtendedState(),
            new JsonSchemaFeature(),
            new AbilitiesFeature(),
            new PresentationFeature(),
            new RegionLoader()
        );

        $yamlContent = <<<'YAML'
context:
  schema:
    - name: testField
      type: string
      default: test
presentations:
  - key: testField
    label: Test Label
    intent: Test Intent
    metadata:
      unit: seconds
      precision: 2
states:
  - name: initial
YAML;

        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);

        // Verify metadata was stored
        $registry = $builder->chainMail->get(PresentationRegistry::class);
        $presentation = $registry->get('testField');

        $this->assertIsArray($presentation->metadata);
        $this->assertSame('seconds', $presentation->metadata['unit']);
        $this->assertSame(2, $presentation->metadata['precision']);
    }
}
