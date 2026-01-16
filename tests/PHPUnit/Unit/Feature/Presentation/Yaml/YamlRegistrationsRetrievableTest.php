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
 * Acceptance Criteria: YAML-registered presentations retrievable via PresentationRegistry.get()
 * Intent: Ensures YAML registrations behave identically to programmatic registrations
 * Criticality: contract
 *
 * @spec specs/features/presentation.yaml:445-448
 */
final class YamlRegistrationsRetrievableTest extends TestCase
{
    public function testYamlRegistrationsRetrievable(): void
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
    - name: yamlField
      type: string
      default: test
presentations:
  - key: yamlField
    label: YAML Label
    intent: YAML Intent
    metadata:
      format: uppercase
states:
  - name: initial
YAML;

        // Build region with YAML presentations
        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);

        // Retrieve via PresentationRegistry.get()
        $registry = $builder->chainMail->get(PresentationRegistry::class);
        $presentation = $registry->get('yamlField');

        $this->assertNotNull($presentation, 'YAML-registered presentation must be retrievable via registry.get()');
        $this->assertSame('yamlField', $presentation->key);
        $this->assertSame('YAML Label', $presentation->label);
        $this->assertSame('YAML Intent', $presentation->intent);
        $this->assertIsArray($presentation->metadata);
        $this->assertSame('uppercase', $presentation->metadata['format']);
    }
}
