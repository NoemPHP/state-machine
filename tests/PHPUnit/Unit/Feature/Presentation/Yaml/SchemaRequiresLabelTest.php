<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Yaml;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Schema validates 'label' as required string in presentation definitions
 * Intent: Enforces human-readable name requirement for UI rendering
 * Criticality: contract
 */
final class SchemaRequiresLabelTest extends TestCase
{
    public function testLabelIsRequiredInPresentationDefinition(): void
    {
        $this->expectException(\Exception::class);

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
    intent: Test Intent
states:
  - name: initial
YAML;

        // Should throw exception for missing 'label'
        $builder->build(['loader' => ['yaml' => $yamlContent]]);
    }

    public function testLabelMustBeString(): void
    {
        $this->expectException(\Exception::class);

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
    label: 123
    intent: Test Intent
states:
  - name: initial
YAML;

        // Should throw exception for non-string 'label'
        $builder->build(['loader' => ['yaml' => $yamlContent]]);
    }
}
