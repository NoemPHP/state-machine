<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Yaml;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\Presentation\SchemaNotFoundException;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: YAML presentation registration validates schema existence at build time
 * Intent: Catches unvalidated field exposure during build phase, failing fast before execution
 * Criticality: contract
 *
 * @spec specs/features/presentation.yaml:455-458
 */
final class YamlValidatesSchemaAtBuildTest extends TestCase
{
    public function testYamlValidatesSchemaAtBuild(): void
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
    - name: validField
      type: string
      default: test
presentations:
  - key: missingSchemaField
    label: Invalid Presentation
    intent: This field has no schema definition
states:
  - name: initial
YAML;

        // Expect SchemaNotFoundException during build
        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage('missingSchemaField');

        // Build should fail during presentation registration
        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);
    }
}
