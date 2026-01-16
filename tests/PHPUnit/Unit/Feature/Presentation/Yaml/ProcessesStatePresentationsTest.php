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
 * Acceptance Criteria: EnhanceRegionBuilder processes state-level presentations during build phase
 * Intent: Registers state-scoped presentations with auto-generated predicate checking currentState
 * Criticality: contract
 *
 * @spec specs/features/presentation.yaml:425-428
 */
final class ProcessesStatePresentationsTest extends TestCase
{
    public function testProcessesStatePresentations(): void
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
    - name: stateField
      type: string
      default: test
states:
  - name: processing
    presentations:
      - key: stateField
        label: Processing Field
        intent: Field visible only in processing state
YAML;

        // Build region with state-level presentations in YAML
        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);

        // Verify presentation was registered during build phase
        $registry = $builder->chainMail->get(PresentationRegistry::class);
        $presentation = $registry->get('stateField');

        $this->assertNotNull($presentation, 'State-level presentation should be registered during build');
        $this->assertSame('stateField', $presentation->key);
        $this->assertSame('Processing Field', $presentation->label);
        $this->assertSame('Field visible only in processing state', $presentation->intent);
        $this->assertNotNull($presentation->predicate, 'State-level presentation should have auto-generated predicate');
    }
}
