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
 * Acceptance Criteria: Schema validates 'key' as required string in presentation definitions
 * Intent: Enforces context field identifier requirement for every declared presentation
 * Criticality: contract
 */
final class SchemaRequiresKeyTest extends TestCase
{
    public function testKeyIsRequiredInPresentationDefinition(): void
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
  - label: Test Label
    intent: Test Intent
states:
  - name: initial
YAML;

        // Should throw exception for missing 'key'
        $builder->build(['loader' => ['yaml' => $yamlContent]]);
    }

    public function testKeyMustBeString(): void
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
  - key: 123
    label: Test Label
    intent: Test Intent
states:
  - name: initial
YAML;

        // Should throw exception for non-string 'key'
        $builder->build(['loader' => ['yaml' => $yamlContent]]);
    }
}
