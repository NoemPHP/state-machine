<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Presentation;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use Noem\State\Runtime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Complete workflow demonstrating state-scoped presentations with predicates
 * Intent: Validates automatic predicate generation and state-based visibility filtering
 * Criticality: integration
 */
#[CoversClass(PresentationFeature::class)]
class StateScopedPresentationsWorkflowTest extends TestCase
{
    public function testStateScopedPresentationsFilterByState(): void
    {
        $this->markTestSkipped('YAML presentations support not yet implemented.');

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new SubscriptionFeature(),
            new MessageFeature(),
            new ExtendedState(),
            new JsonSchemaFeature(),
            new AsyncFeature(),
            new AbilitiesFeature(),
            new PresentationFeature(),
            new TransitionsFeature(),
            new RegionLoader()
        );

        $yamlContent = <<<'YAML'
context:
  schema:
    - name: globalField
      type: string
      default: global
    - name: initialField
      type: string
      default: initial-only
    - name: processingField
      type: string
      default: processing-only
presentations:
  - key: globalField
    label: Global
    intent: Visible in all states
states:
  - name: initial
    presentations:
      - key: initialField
        label: Initial Field
        intent: Only visible in initial state
    transitions:
      - event: start
        target: processing
  - name: processing
    presentations:
      - key: processingField
        label: Processing Field
        intent: Only visible in processing state
    onEnter: |
      # Enumerate in processing state
      $this->abilities('enumerate-presentations')->then(function($response) {
          $this->set('processingEnumeration', $response->parameters);
          $this->trigger('done');
      });
      yield;
    transitions:
      - event: done
        target: final
  - name: final
YAML;

        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);
        $runtime = new Runtime($region);

        // Start in initial state
        $this->assertSame('initial', $region->currentState);

        // Transition to processing
        $runtime->trigger('start');
        $runtime->run();

        $result = $region->get('processingEnumeration');

        // Verify state-scoped filtering
        $this->assertIsArray($result);
        $this->assertArrayHasKey('presentations', $result);

        $presentations = $result['presentations'];
        $keys = array_column($presentations, 'key');

        // Global field should be visible
        $this->assertContains('globalField', $keys);

        // Processing field should be visible (current state is processing)
        $this->assertContains('processingField', $keys);

        // Initial field should NOT be visible (no longer in initial state)
        $this->assertNotContains('initialField', $keys);
    }

    public function testStatePredicateChecksCurrentState(): void
    {
        $this->markTestSkipped('YAML presentations support not yet implemented.');

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new SubscriptionFeature(),
            new MessageFeature(),
            new ExtendedState(),
            new JsonSchemaFeature(),
            new AsyncFeature(),
            new AbilitiesFeature(),
            new PresentationFeature(),
            new TransitionsFeature(),
            new RegionLoader()
        );

        $yamlContent = <<<'YAML'
context:
  schema:
    - name: stateSpecific
      type: string
      default: test
states:
  - name: state1
    presentations:
      - key: stateSpecific
        label: State 1 Field
        intent: Only in state1
    transitions:
      - event: next
        target: state2
  - name: state2
    onEnter: |
      # Check enumeration from state2
      $this->abilities('enumerate-presentations')->then(function($response) {
          $this->set('state2Enumeration', $response->parameters);
          $this->trigger('done');
      });
      yield;
    transitions:
      - event: done
        target: final
  - name: final
YAML;

        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);
        $runtime = new Runtime($region);

        // Transition from state1 to state2
        $runtime->trigger('next');
        $runtime->run();

        $result = $region->get('state2Enumeration');

        // Presentation declared in state1 should NOT be visible in state2
        $this->assertIsArray($result);
        $presentations = $result['presentations'];
        $keys = array_column($presentations, 'key');

        $this->assertNotContains('stateSpecific', $keys);
    }
}
