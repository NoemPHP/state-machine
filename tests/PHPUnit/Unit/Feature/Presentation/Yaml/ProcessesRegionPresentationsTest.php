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
 * Acceptance Criteria: EnhanceRegionBuilder processes region-level presentations during build phase
 * Intent: Registers global presentations before machine starts for immediate availability
 * Criticality: contract
 *
 * @spec specs/features/presentation.yaml:420-423
 */
final class ProcessesRegionPresentationsTest extends TestCase
{
    public function testProcessesRegionPresentations(): void
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
    intent: Test Intent for region-level presentation
states:
  - name: initial
YAML;

        // Build region with region-level presentations in YAML
        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);

        // Verify presentation was registered during build phase
        $registry = $builder->chainMail->get(PresentationRegistry::class);
        $presentation = $registry->get('testField');

        $this->assertNotNull($presentation, 'Region-level presentation should be registered during build');
        $this->assertSame('testField', $presentation->key);
        $this->assertSame('Test Label', $presentation->label);
        $this->assertSame('Test Intent for region-level presentation', $presentation->intent);
    }
}
