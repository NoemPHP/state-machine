<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Presentation\Discovery;

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

#[CoversClass(PresentationFeature::class)]
class GetPresentedStateIntegrationTest extends TestCase
{
    public function testGetPresentedStateRetrievesValuesFromExtendedState(): void
    {
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
    - name: count
      type: integer
      default: 99
    - name: name
      type: string
      default: TestName
states:
  - name: initial
    transitions:
      - event: start
        target: processing
  - name: processing
    onEnter: |
      $this->presentation('count', 'Count', 'Test count');
      $this->presentation('name', 'Name', 'Test name');

      # Get all presented state
      $this->abilities('get-presented-state')->then(function($response) {
          $this->set('result', $response->parameters);
      });
      yield;

      $this->trigger('done');
    transitions:
      - event: done
        target: final
  - name: final
YAML;

        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);
        $runtime = new Runtime($region);

        $runtime->trigger('start');
        $runtime->run();

        $result = $region->get('result');

        // Test: Returns values from ExtendedState
        $this->assertArrayHasKey('values', $result);
        $values = $result['values'];

        $this->assertArrayHasKey('count', $values);
        $this->assertEquals(99, $values['count']['value']);

        $this->assertArrayHasKey('name', $values);
        $this->assertEquals('TestName', $values['name']['value']);

        // Test: Includes metadata
        $this->assertEquals('Count', $values['count']['label']);
        $this->assertEquals('Test count', $values['count']['intent']);
    }

    public function testGetPresentedStateAcceptsKeysParameter(): void
    {
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
    - name: key1
      type: string
      default: value1
    - name: key2
      type: string
      default: value2
    - name: key3
      type: string
      default: value3
states:
  - name: initial
    transitions:
      - event: start
        target: processing
  - name: processing
    onEnter: |
      $this->presentation('key1', 'Label 1', 'Intent 1');
      $this->presentation('key2', 'Label 2', 'Intent 2');
      $this->presentation('key3', 'Label 3', 'Intent 3');

      # Get specific keys only
      $this->abilities('get-presented-state', ['keys' => ['key1', 'key3']])->then(function($response) {
          $this->set('result', $response->parameters);
      });
      yield;

      $this->trigger('done');
    transitions:
      - event: done
        target: final
  - name: final
YAML;

        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);
        $runtime = new Runtime($region);

        $runtime->trigger('start');
        $runtime->run();

        $result = $region->get('result');
        $values = $result['values'];

        // Test: Returns subset
        $this->assertArrayHasKey('key1', $values);
        $this->assertArrayHasKey('key3', $values);
        $this->assertArrayNotHasKey('key2', $values);
    }

    public function testGetPresentedStateReturnsAllWhenKeysOmitted(): void
    {
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
    - name: key1
      type: string
      default: value1
    - name: key2
      type: string
      default: value2
states:
  - name: initial
    transitions:
      - event: start
        target: processing
  - name: processing
    onEnter: |
      $this->presentation('key1', 'Label 1', 'Intent 1');
      $this->presentation('key2', 'Label 2', 'Intent 2');

      # Get all without specifying keys parameter
      $this->abilities('get-presented-state')->then(function($response) {
          $this->set('result', $response->parameters);
      });
      yield;

      $this->trigger('done');
    transitions:
      - event: done
        target: final
  - name: final
YAML;

        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);
        $runtime = new Runtime($region);

        $runtime->trigger('start');
        $runtime->run();

        $result = $region->get('result');
        $values = $result['values'];

        // Test: Returns all presentations
        $this->assertCount(2, $values);
        $this->assertArrayHasKey('key1', $values);
        $this->assertArrayHasKey('key2', $values);
    }

    public function testGetPresentedStateRespectsPredicateFiltering(): void
    {
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
    - name: visible
      type: string
      default: visibleValue
    - name: hidden
      type: string
      default: hiddenValue
states:
  - name: initial
    transitions:
      - event: start
        target: processing
  - name: processing
    onEnter: |
      # Presentation with true predicate
      $this->presentation(
          'visible',
          'Visible',
          'Should be visible',
          null,
          fn($r) => true
      );

      # Presentation with false predicate
      $this->presentation(
          'hidden',
          'Hidden',
          'Should be hidden',
          null,
          fn($r) => false
      );

      $this->abilities('get-presented-state')->then(function($response) {
          $this->set('result', $response->parameters);
      });
      yield;

      $this->trigger('done');
    transitions:
      - event: done
        target: final
  - name: final
YAML;

        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);
        $runtime = new Runtime($region);

        $runtime->trigger('start');
        $runtime->run();

        $result = $region->get('result');
        $values = $result['values'];

        // Test: Includes values with true predicates
        $this->assertArrayHasKey('visible', $values);
        $this->assertEquals('visibleValue', $values['visible']['value']);

        // Test: Excludes values with false predicates
        $this->assertArrayNotHasKey('hidden', $values);
    }

    public function testGetPresentedStateIgnoresUnknownKeys(): void
    {
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
    - name: existing
      type: string
      default: existingValue
states:
  - name: initial
    transitions:
      - event: start
        target: processing
  - name: processing
    onEnter: |
      $this->presentation('existing', 'Existing', 'Exists');

      # Request unknown key without error
      $this->abilities('get-presented-state', ['keys' => ['existing', 'nonexistent']])->then(function($response) {
          $this->set('result', $response->parameters);
          $this->set('noError', true);
      });
      yield;

      $this->trigger('done');
    transitions:
      - event: done
        target: final
  - name: final
YAML;

        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);
        $runtime = new Runtime($region);

        $runtime->trigger('start');
        $runtime->run();

        // Test: No error occurred
        $this->assertTrue($region->get('noError'));

        // Test: Returns only existing key
        $result = $region->get('result');
        $values = $result['values'];

        $this->assertArrayHasKey('existing', $values);
        $this->assertArrayNotHasKey('nonexistent', $values);
    }

    public function testGetPresentedStateAccessibleViaBoundAccess(): void
    {
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
    - name: testKey
      type: string
      default: testValue
states:
  - name: initial
    transitions:
      - event: start
        target: processing
  - name: processing
    onEnter: |
      $this->presentation('testKey', 'Test Label', 'Test Intent');

      # Access via $this->abilities() in state callback
      $this->abilities('get-presented-state')->then(function($response) {
          $this->set('accessible', true);
          $this->set('result', $response->parameters);
      });
      yield;

      $this->trigger('done');
    transitions:
      - event: done
        target: final
  - name: final
YAML;

        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);
        $runtime = new Runtime($region);

        $runtime->trigger('start');
        $runtime->run();

        // Verify ability was accessible and executed
        $this->assertTrue($region->get('accessible'));
        $this->assertNotNull($region->get('result'));
    }

    public function testGetPresentedStateUsesMessageCorrelation(): void
    {
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
    - name: testKey
      type: string
      default: testValue
states:
  - name: initial
    transitions:
      - event: start
        target: processing
  - name: processing
    onEnter: |
      $this->presentation('testKey', 'Test Label', 'Test Intent');

      # Verify .then() callback pattern works
      $this->abilities('get-presented-state')->then(function($response) {
          $this->set('correlationWorked', true);
      });
      yield;

      $this->trigger('done');
    transitions:
      - event: done
        target: final
  - name: final
YAML;

        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);
        $runtime = new Runtime($region);

        $runtime->trigger('start');
        $runtime->run();

        // Verify correlation pattern worked via .then() callback
        $this->assertTrue($region->get('correlationWorked'));
    }

    public function testGetPresentedStateReturnsMetadataWithValues(): void
    {
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
    - name: testKey
      type: string
      default: testValue
states:
  - name: initial
    transitions:
      - event: start
        target: processing
  - name: processing
    onEnter: |
      $this->presentation('testKey', 'Test Label', 'Test Intent', ['unit' => 'ms']);

      $this->abilities('get-presented-state')->then(function($response) {
          $this->set('result', $response->parameters);
      });
      yield;

      $this->trigger('done');
    transitions:
      - event: done
        target: final
  - name: final
YAML;

        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);
        $runtime = new Runtime($region);

        $runtime->trigger('start');
        $runtime->run();

        $result = $region->get('result');
        $values = $result['values'];

        // Test: Returns metadata along with value
        $this->assertArrayHasKey('testKey', $values);
        $this->assertArrayHasKey('value', $values['testKey']);
        $this->assertArrayHasKey('label', $values['testKey']);
        $this->assertArrayHasKey('intent', $values['testKey']);
        $this->assertArrayHasKey('metadata', $values['testKey']);

        $this->assertEquals('testValue', $values['testKey']['value']);
        $this->assertEquals('Test Label', $values['testKey']['label']);
        $this->assertEquals('Test Intent', $values['testKey']['intent']);
        $this->assertEquals(['unit' => 'ms'], $values['testKey']['metadata']);
    }
}
