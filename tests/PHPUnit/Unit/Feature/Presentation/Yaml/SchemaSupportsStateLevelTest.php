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
 * Acceptance Criteria: RegionLoader Schema supports 'presentations' key at state level
 * Intent: Enables state-scoped presentation declarations with automatic predicate generation
 * Criticality: contract
 */
final class SchemaSupportsStateLevelTest extends TestCase
{
    public function testStateLevelPresentationsKeySupported(): void
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
states:
  - name: initial
    presentations:
      - key: testField
        label: Test Label
        intent: Test Intent
YAML;

        // Should not throw an exception for state-level presentations
        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);

        $this->assertNotNull($region);
    }
}
