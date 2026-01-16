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
use Noem\State\StandardRuntime;
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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
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

          };
    transitions:
      - target: final
  - name: final
YAML;

        $helpers = ['php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper()];
        $schema = [
            'throwing' => ['name' => 'throwing', 'type' => 'string', 'default' => 'value'],
            'normal' => ['name' => 'normal', 'type' => 'string', 'default' => 'value'],
        ];
        $region = $builder->build([
            'loader' => [
                'yaml' => $yamlContent,
                'yamlHelpers' => $helpers,
                'context' => ['schema' => array_values($schema)],
            ]
        ]);

        // Manually populate schemas in PresentationRegistry
        $presentationRegistry = $builder->chainMail->get(\Noem\State\Feature\Presentation\PresentationRegistry::class);
        $presentationRegistry->setSchemas($schema);

        $runtime = new StandardRuntime($region);

        $runtime->run();

        // Access context through Meta chain since Region doesn't have get() method
        $meta = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metaParams = new \Noem\State\Chains\Params\Meta($region, \Noem\State\Feature\ExtendedState\ContextMetaType::get());
        $context = $meta->call($metaParams);

        // Test enumerate-presentations excludes throwing predicate
        $enumerateResult = $context['enumerateResult'];
        $keys = array_column($enumerateResult['presentations'], 'key');
        $this->assertNotContains('throwing', $keys);
        $this->assertContains('normal', $keys);

        // Test get-presented-state excludes throwing predicate
        $getStateResult = $context['getStateResult'];
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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
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

          };
    transitions:
      - target: final
  - name: final
YAML;

        $helpers = ['php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper()];
        $schema = [
            'testKey' => ['name' => 'testKey', 'type' => 'string', 'default' => 'testValue'],
        ];
        $region = $builder->build([
            'loader' => [
                'yaml' => $yamlContent,
                'yamlHelpers' => $helpers,
                'context' => ['schema' => array_values($schema)],
            ]
        ]);

        // Manually populate schemas in PresentationRegistry
        $presentationRegistry = $builder->chainMail->get(\Noem\State\Feature\Presentation\PresentationRegistry::class);
        $presentationRegistry->setSchemas($schema);

        $runtime = new StandardRuntime($region);

        $runtime->run();

        // Access context through Meta chain since Region doesn't have get() method
        $meta = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metaParams = new \Noem\State\Chains\Params\Meta($region, \Noem\State\Feature\ExtendedState\ContextMetaType::get());
        $context = $meta->call($metaParams);

        // Verify predicate received Region parameter
        $this->assertTrue($context['receivedRegion']);
        $this->assertTrue($context['hasCurrentState']);
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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
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

          };
    transitions:
      - target: final
  - name: final
YAML;

        $helpers = ['php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper()];
        $schema = [
            'dynamic' => ['name' => 'dynamic', 'type' => 'string', 'default' => 'testValue'],
            'counter' => ['name' => 'counter', 'type' => 'integer', 'default' => 0],
        ];
        $region = $builder->build([
            'loader' => [
                'yaml' => $yamlContent,
                'yamlHelpers' => $helpers,
                'context' => ['schema' => array_values($schema)],
            ]
        ]);

        // Manually populate schemas in PresentationRegistry
        $meta = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $presentationRegistry = $builder->chainMail->get(\Noem\State\Feature\Presentation\PresentationRegistry::class);
        $presentationRegistry->setSchemas($schema);

        // Register presentation with predicate checking context after build
        $presentationRegistry->register(
            new \Noem\State\Feature\Presentation\RegionPresentation(
                key: 'dynamic',
                label: 'Dynamic',
                intent: 'Visibility changes',
                metadata: null,
                predicate: function($r) use ($meta) {
                    $metaParams = new \Noem\State\Chains\Params\Meta($r, \Noem\State\Feature\ExtendedState\ContextMetaType::get());
                    $context = $meta->call($metaParams);
                    return $context['counter'] > 0;
                }
            )
        );

        $runtime = new StandardRuntime($region);

        $runtime->run();

        // Access context through Meta chain since Region doesn't have get() method
        $meta = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metaParams = new \Noem\State\Chains\Params\Meta($region, \Noem\State\Feature\ExtendedState\ContextMetaType::get());
        $context = $meta->call($metaParams);

        // Test: First enumeration excludes (counter=0)
        $firstResult = $context['firstEnumeration'];
        $firstKeys = array_column($firstResult['presentations'], 'key');
        $this->assertNotContains('dynamic', $firstKeys);

        // Test: Second enumeration includes (counter=1)
        $secondResult = $context['secondEnumeration'];
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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
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

          };
    transitions:
      - target: final
  - name: final
YAML;

        $helpers = ['php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper()];
        $schema = [
            'alwaysVisible' => ['name' => 'alwaysVisible', 'type' => 'string', 'default' => 'value'],
        ];
        $region = $builder->build([
            'loader' => [
                'yaml' => $yamlContent,
                'yamlHelpers' => $helpers,
                'context' => ['schema' => array_values($schema)],
            ]
        ]);

        // Manually populate schemas in PresentationRegistry
        $presentationRegistry = $builder->chainMail->get(\Noem\State\Feature\Presentation\PresentationRegistry::class);
        $presentationRegistry->setSchemas($schema);

        $runtime = new StandardRuntime($region);

        $runtime->run();

        // Access context through Meta chain since Region doesn't have get() method
        $meta = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metaParams = new \Noem\State\Chains\Params\Meta($region, \Noem\State\Feature\ExtendedState\ContextMetaType::get());
        $context = $meta->call($metaParams);

        // Test: enumerate-presentations includes null predicate
        $enumerateResult = $context['enumerateResult'];
        $keys = array_column($enumerateResult['presentations'], 'key');
        $this->assertContains('alwaysVisible', $keys);

        // Test: get-presented-state includes null predicate
        $getStateResult = $context['getStateResult'];
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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
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

          };
    transitions:
      - target: final
  - name: final
YAML;

        $helpers = ['php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper()];
        $schema = [
            'visible1' => ['name' => 'visible1', 'type' => 'string', 'default' => 'value1'],
            'hidden1' => ['name' => 'hidden1', 'type' => 'string', 'default' => 'value2'],
            'visible2' => ['name' => 'visible2', 'type' => 'string', 'default' => 'value3'],
            'hidden2' => ['name' => 'hidden2', 'type' => 'string', 'default' => 'value4'],
        ];
        $region = $builder->build([
            'loader' => [
                'yaml' => $yamlContent,
                'yamlHelpers' => $helpers,
                'context' => ['schema' => array_values($schema)],
            ]
        ]);

        // Manually populate schemas in PresentationRegistry
        $presentationRegistry = $builder->chainMail->get(\Noem\State\Feature\Presentation\PresentationRegistry::class);
        $presentationRegistry->setSchemas($schema);

        $runtime = new StandardRuntime($region);

        $runtime->run();

        // Access context through Meta chain since Region doesn't have get() method
        $meta = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metaParams = new \Noem\State\Chains\Params\Meta($region, \Noem\State\Feature\ExtendedState\ContextMetaType::get());
        $context = $meta->call($metaParams);

        // Get keys from both abilities
        $enumerateResult = $context['enumerateResult'];
        $enumerateKeys = array_column($enumerateResult['presentations'], 'key');
        sort($enumerateKeys);

        $getStateResult = $context['getStateResult'];
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
