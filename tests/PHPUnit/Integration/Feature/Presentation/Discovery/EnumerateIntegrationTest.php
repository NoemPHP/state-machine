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
class EnumerateIntegrationTest extends TestCase
{
    public function testEnumeratePresentationsReturnsArrayWithSchemas(): void
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
            new TransitionsFeature()
        );

        // Define schema
        $builder->setSchema([
            'userCount' => ['type' => 'integer', 'default' => 42],
            'status' => ['type' => 'string', 'default' => 'active'],
        ]);

        // Register presentations
        $builder->presentation('userCount', 'Active Users', 'Current user count');
        $builder->presentation('status', 'Status', 'System status');

        // Build state machine
        $builder
            ->setStates('initial', 'processing', 'final')
            ->setInitial('initial')
            ->addTransition('initial', 'start', 'processing')
            ->addTransition('processing', 'done', 'final')
            ->onEnter('processing', function (object $trigger): \Generator {
                // Call enumerate-presentations ability
                $this->abilities('enumerate-presentations')->then(function ($response) {
                    $this->set('enumerateResult', $response->parameters);
                });
                yield;

                // Transition to final
                $this->trigger('done');
            });

        $region = $builder->build();
        $runtime = new Runtime($region);

        $runtime->trigger('start');
        $runtime->run();

        $result = $region->get('enumerateResult');

        // Test: Returns array
        $this->assertIsArray($result);
        $this->assertArrayHasKey('presentations', $result);

        // Test: Contains presentations
        $this->assertCount(2, $result['presentations']);

        // Test: Each presentation has schema
        foreach ($result['presentations'] as $presentation) {
            $this->assertArrayHasKey('key', $presentation);
            $this->assertArrayHasKey('label', $presentation);
            $this->assertArrayHasKey('intent', $presentation);
            $this->assertArrayHasKey('schema', $presentation);

            // Schema from JsonSchemaFeature
            $this->assertIsArray($presentation['schema']);
            $this->assertArrayHasKey('type', $presentation['schema']);
        }

        // Test: Serializes properly (no predicate in output)
        $this->assertArrayNotHasKey('predicate', $result['presentations'][0]);
    }

    public function testEnumeratePresentationsEvaluatesPredicates(): void
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
      default: test
    - name: hidden
      type: string
      default: test
    - name: nullPred
      type: string
      default: test
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

      # Presentation with null predicate
      $this->presentation(
          'nullPred',
          'Null Predicate',
          'Should be visible',
          null,
          null
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

        $result = $region->get('result');
        $presentations = $result['presentations'];

        // Test: Includes true predicate
        $keys = array_column($presentations, 'key');
        $this->assertContains('visible', $keys);

        // Test: Excludes false predicate
        $this->assertNotContains('hidden', $keys);

        // Test: Includes null predicate (always visible)
        $this->assertContains('nullPred', $keys);
    }

    public function testEnumeratePresentationsAccessibleViaBoundAccess(): void
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
      $this->abilities('enumerate-presentations')->then(function($response) {
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

    public function testEnumeratePresentationsUsesMessageCorrelation(): void
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
      $callbackExecuted = false;
      $this->abilities('enumerate-presentations')->then(function($response) use (&$callbackExecuted) {
          $callbackExecuted = true;
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
}
