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

/**
 * Integration tests focused on predicate evaluation behavior
 * across both enumerate-presentations and get-presented-state abilities
 */
#[CoversClass(PresentationFeature::class)]
class PredicateFilteringIntegrationTest extends TestCase
{
    public function testPredicateExceptionsTreatedAsFalse(): void
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
    - name: throwing
      type: string
      default: value
    - name: normal
      type: string
      default: value
states:
  - name: initial
    transitions:
      - event: start
        target: processing
  - name: processing
    onEnter: |
      # Presentation with throwing predicate
      $this->presentation(
          'throwing',
          'Throws Exception',
          'Predicate throws',
          null,
          fn($r) => throw new \RuntimeException('Predicate error')
      );

      # Normal presentation
      $this->presentation('normal', 'Normal', 'Normal predicate', null, fn($r) => true);

      $this->abilities('enumerate-presentations')->then(function($response) {
          $this->set('enumerateResult', $response->parameters);
      });
      yield;

      $this->abilities('get-presented-state')->then(function($response) {
          $this->set('getStateResult', $response->parameters);
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

        // Test enumerate-presentations excludes throwing predicate
        $enumerateResult = $region->get('enumerateResult');
        $keys = array_column($enumerateResult['presentations'], 'key');
        $this->assertNotContains('throwing', $keys);
        $this->assertContains('normal', $keys);

        // Test get-presented-state excludes throwing predicate
        $getStateResult = $region->get('getStateResult');
        $values = $getStateResult['values'];
        $this->assertArrayNotHasKey('throwing', $values);
        $this->assertArrayHasKey('normal', $values);
    }

    public function testPredicateReceivesRegionParameter(): void
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
      # Predicate that inspects Region parameter
      $this->presentation(
          'testKey',
          'Test',
          'Checks region',
          null,
          function($region) {
              # Verify we received Region instance
              $this->set('receivedRegion', $region instanceof \Noem\State\Region);
              $this->set('hasCurrentState', method_exists($region, 'currentState'));
              return true;
          }
      );

      $this->abilities('enumerate-presentations')->then(function($response) {
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

        // Verify predicate received Region parameter
        $this->assertTrue($region->get('receivedRegion'));
        $this->assertTrue($region->get('hasCurrentState'));
    }

    public function testPredicateEvaluatedLazilyAtEnumerationTime(): void
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
    - name: dynamic
      type: string
      default: testValue
    - name: counter
      type: integer
      default: 0
states:
  - name: initial
    transitions:
      - event: start
        target: processing
  - name: processing
    onEnter: |
      # Register presentation with predicate checking context
      $this->presentation(
          'dynamic',
          'Dynamic',
          'Visibility changes',
          null,
          fn($r) => $r->get('counter') > 0
      );

      # First enumeration - counter is 0, should be hidden
      $this->abilities('enumerate-presentations')->then(function($response) {
          $this->set('firstEnumeration', $response->parameters);
      });
      yield;

      # Increment counter
      $this->set('counter', 1);

      # Second enumeration - counter is 1, should be visible
      $this->abilities('enumerate-presentations')->then(function($response) {
          $this->set('secondEnumeration', $response->parameters);
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

        // Test: First enumeration excludes (counter=0)
        $firstResult = $region->get('firstEnumeration');
        $firstKeys = array_column($firstResult['presentations'], 'key');
        $this->assertNotContains('dynamic', $firstKeys);

        // Test: Second enumeration includes (counter=1)
        $secondResult = $region->get('secondEnumeration');
        $secondKeys = array_column($secondResult['presentations'], 'key');
        $this->assertContains('dynamic', $secondKeys);
    }

    public function testNullPredicateAlwaysVisible(): void
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
    - name: alwaysVisible
      type: string
      default: value
states:
  - name: initial
    transitions:
      - event: start
        target: processing
  - name: processing
    onEnter: |
      # Presentation with null predicate (no predicate)
      $this->presentation('alwaysVisible', 'Always', 'No predicate', null, null);

      $this->abilities('enumerate-presentations')->then(function($response) {
          $this->set('enumerateResult', $response->parameters);
      });
      yield;

      $this->abilities('get-presented-state')->then(function($response) {
          $this->set('getStateResult', $response->parameters);
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

        // Test: enumerate-presentations includes null predicate
        $enumerateResult = $region->get('enumerateResult');
        $keys = array_column($enumerateResult['presentations'], 'key');
        $this->assertContains('alwaysVisible', $keys);

        // Test: get-presented-state includes null predicate
        $getStateResult = $region->get('getStateResult');
        $values = $getStateResult['values'];
        $this->assertArrayHasKey('alwaysVisible', $values);
    }

    public function testPredicateFilteringConsistentBetweenEnumerateAndGetState(): void
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
    - name: visible1
      type: string
      default: value1
    - name: hidden1
      type: string
      default: value2
    - name: visible2
      type: string
      default: value3
    - name: hidden2
      type: string
      default: value4
states:
  - name: initial
    transitions:
      - event: start
        target: processing
  - name: processing
    onEnter: |
      $this->presentation('visible1', 'V1', 'Visible', null, fn($r) => true);
      $this->presentation('hidden1', 'H1', 'Hidden', null, fn($r) => false);
      $this->presentation('visible2', 'V2', 'Visible', null, fn($r) => true);
      $this->presentation('hidden2', 'H2', 'Hidden', null, fn($r) => false);

      $this->abilities('enumerate-presentations')->then(function($response) {
          $this->set('enumerateResult', $response->parameters);
      });
      yield;

      $this->abilities('get-presented-state')->then(function($response) {
          $this->set('getStateResult', $response->parameters);
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

        // Get keys from both abilities
        $enumerateResult = $region->get('enumerateResult');
        $enumerateKeys = array_column($enumerateResult['presentations'], 'key');
        sort($enumerateKeys);

        $getStateResult = $region->get('getStateResult');
        $getStateKeys = array_keys($getStateResult['values']);
        sort($getStateKeys);

        // Test: Both abilities return same set of visible keys
        $this->assertEquals($enumerateKeys, $getStateKeys);

        // Test: Both include visible presentations
        $this->assertContains('visible1', $enumerateKeys);
        $this->assertContains('visible2', $enumerateKeys);

        // Test: Both exclude hidden presentations
        $this->assertNotContains('hidden1', $enumerateKeys);
        $this->assertNotContains('hidden2', $enumerateKeys);
    }
}
