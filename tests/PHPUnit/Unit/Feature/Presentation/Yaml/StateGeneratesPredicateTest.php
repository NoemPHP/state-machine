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
 * Acceptance Criteria: State-level YAML presentations auto-generate predicate checking currentState
 * Intent: Automatically filters state-scoped presentations based on machine's current state
 * Criticality: contract
 *
 * @spec specs/features/presentation.yaml:430-433
 */
final class StateGeneratesPredicateTest extends TestCase
{
    public function testStateGeneratesPredicate(): void
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
        intent: State-scoped field
YAML;

        // Build region with state-level presentations
        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);

        // Verify predicate was auto-generated
        $registry = $builder->chainMail->get(PresentationRegistry::class);
        $presentation = $registry->get('stateField');

        $this->assertNotNull($presentation, 'State-level presentation should be registered');
        $this->assertNotNull($presentation->predicate, 'State-level presentation must have auto-generated predicate');
        $this->assertIsCallable($presentation->predicate, 'Predicate must be callable');
    }
}
