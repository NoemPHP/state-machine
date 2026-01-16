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
 * Acceptance Criteria: Schema validates 'intent' as required string in presentation definitions
 * Intent: Enforces semantic description requirement for context-aware rendering
 * Criticality: contract
 */
final class SchemaRequiresIntentTest extends TestCase
{
    public function testIntentIsRequiredInPresentationDefinition(): void
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
    label: Test Label
states:
  - name: initial
YAML;

        // Should throw exception for missing 'intent'
        $builder->build(['loader' => ['yaml' => $yamlContent]]);
    }

    public function testIntentMustBeString(): void
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
    label: Test Label
    intent: 123
states:
  - name: initial
YAML;

        // Should throw exception for non-string 'intent'
        $builder->build(['loader' => ['yaml' => $yamlContent]]);
    }
}
