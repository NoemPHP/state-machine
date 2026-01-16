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
 * Acceptance Criteria: RegionLoader Schema supports 'presentations' key at region level
 * Intent: Enables global presentation declarations in Holon YAML files
 * Criticality: contract
 */
final class SchemaSupportsRegionLevelTest extends TestCase
{
    public function testRegionLevelPresentationsKeySupported(): void
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

        // Should not throw an exception for region-level presentations
        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);

        $this->assertNotNull($region);
    }
}
