<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Yaml;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: State-level auto-generated predicate checks currentState matches state name
 * Intent: Returns true only when machine is in the state where presentation was declared
 * Criticality: contract
 *
 * @spec specs/features/presentation.yaml:435-438
 */
final class StatePredicateChecksCurrentStateTest extends TestCase
{
    public function testStatePredicateChecksCurrentState(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new ExtendedState(),
            new JsonSchemaFeature(),
            new AbilitiesFeature(),
            new PresentationFeature(),
            new TransitionsFeature(),
            new RegionLoader()
        );

        $yamlContent = <<<'YAML'
context:
  schema:
    - name: stateField
      type: string
      default: test
initial: idle
states:
  - name: idle
    transitions:
      - target: processing
  - name: processing
    presentations:
      - key: stateField
        label: Processing Field
        intent: Visible only in processing state
YAML;

        // Build region
        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);

        // Get the auto-generated predicate
        $registry = $builder->chainMail->get(PresentationRegistry::class);
        $presentation = $registry->get('stateField');

        $this->assertNotNull($presentation);
        $this->assertNotNull($presentation->predicate);

        // Test predicate returns false when in different state (idle)
        $this->assertSame('idle', $region->currentState());
        $predicateResult = ($presentation->predicate)($region);
        $this->assertFalse($predicateResult, 'Predicate should return false when not in processing state');

        // Transition to processing state using runtime
        $runtime = new StandardRuntime($region);
        $runtime->run();
        $this->assertSame('processing', $region->currentState());

        // Test predicate returns true when in correct state
        $predicateResult = ($presentation->predicate)($region);
        $this->assertTrue($predicateResult, 'Predicate should return true when in processing state');
    }
}
