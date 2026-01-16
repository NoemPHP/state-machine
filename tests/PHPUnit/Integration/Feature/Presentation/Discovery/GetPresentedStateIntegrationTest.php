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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
              $this->presentation('count', 'Count', 'Test count');
              $this->presentation('name', 'Name', 'Test name');

              # Get all presented state
              $this->abilities('get-presented-state')->then(function($response) {
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
            'count' => ['name' => 'count', 'type' => 'integer', 'default' => 99],
            'name' => ['name' => 'name', 'type' => 'string', 'default' => 'TestName'],
        ];

        // Add JsonSchema build step to initialize defaults
        $builder->addBuildStep(new \Noem\State\Feature\JsonSchema\AddJsonSchema(array_values($schema)));

        $region = $builder->build([
            'loader' => [
                'yaml' => $yamlContent,
                'yamlHelpers' => $helpers,
            ]
        ]);

        // Manually populate schemas in PresentationRegistry after build
        $presentationRegistry = $builder->chainMail->get(\Noem\State\Feature\Presentation\PresentationRegistry::class);
        $presentationRegistry->setSchemas($schema);

        $runtime = new StandardRuntime($region);

        $runtime->run();

        // Access context through Meta chain since Region doesn't have get() method
        $meta = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metaParams = new \Noem\State\Chains\Params\Meta($region, \Noem\State\Feature\ExtendedState\ContextMetaType::get());
        $context = $meta->call($metaParams);
        $result = $context['result'];

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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
              $this->presentation('key1', 'Label 1', 'Intent 1');
              $this->presentation('key2', 'Label 2', 'Intent 2');
              $this->presentation('key3', 'Label 3', 'Intent 3');

              # Get specific keys only
              $this->abilities('get-presented-state', ['keys' => ['key1', 'key3']])->then(function($response) {
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
            'key1' => ['name' => 'key1', 'type' => 'string', 'default' => 'value1'],
            'key2' => ['name' => 'key2', 'type' => 'string', 'default' => 'value2'],
            'key3' => ['name' => 'key3', 'type' => 'string', 'default' => 'value3'],
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
        $result = $context['result'];
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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
              $this->presentation('key1', 'Label 1', 'Intent 1');
              $this->presentation('key2', 'Label 2', 'Intent 2');

              # Get all without specifying keys parameter
              $this->abilities('get-presented-state')->then(function($response) {
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
            'key1' => ['name' => 'key1', 'type' => 'string', 'default' => 'value1'],
            'key2' => ['name' => 'key2', 'type' => 'string', 'default' => 'value2'],
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
        $result = $context['result'];
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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
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
          };
    transitions:
      - target: final
  - name: final
YAML;

        $helpers = ['php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper()];
        $schema = [
            'visible' => ['name' => 'visible', 'type' => 'string', 'default' => 'visibleValue'],
            'hidden' => ['name' => 'hidden', 'type' => 'string', 'default' => 'hiddenValue'],
        ];

        // Add JsonSchema build step to initialize defaults
        $builder->addBuildStep(new \Noem\State\Feature\JsonSchema\AddJsonSchema(array_values($schema)));

        $region = $builder->build([
            'loader' => [
                'yaml' => $yamlContent,
                'yamlHelpers' => $helpers,
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
        $result = $context['result'];
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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
              $this->presentation('existing', 'Existing', 'Exists');

              # Request unknown key without error
              $this->abilities('get-presented-state', ['keys' => ['existing', 'nonexistent']])->then(function($response) {
                  $this->set('result', $response->parameters);
                  $this->set('noError', true);
              });
              yield;
          };
    transitions:
      - target: final
  - name: final
YAML;

        $helpers = ['php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper()];
        $schema = [
            'existing' => ['name' => 'existing', 'type' => 'string', 'default' => 'existingValue'],
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

        // Test: No error occurred
        $this->assertTrue($context['noError']);

        // Test: Returns only existing key
        $result = $context['result'];
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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
              $this->presentation('testKey', 'Test Label', 'Test Intent');

              # Access via $this->abilities() in state callback
              $this->abilities('get-presented-state')->then(function($response) {
                  $this->set('accessible', true);
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

        // Verify ability was accessible and executed
        $this->assertTrue($context['accessible']);
        $this->assertNotNull($context['result']);
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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
              $this->presentation('testKey', 'Test Label', 'Test Intent');

              # Verify .then() callback pattern works
              $this->abilities('get-presented-state')->then(function($response) {
                  $this->set('correlationWorked', true);
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

        // Verify correlation pattern worked via .then() callback
        $this->assertTrue($context['correlationWorked']);
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
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
              $this->presentation('testKey', 'Test Label', 'Test Intent', ['unit' => 'ms']);

              $this->abilities('get-presented-state')->then(function($response) {
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

        // Add JsonSchema build step to initialize defaults
        $builder->addBuildStep(new \Noem\State\Feature\JsonSchema\AddJsonSchema(array_values($schema)));

        $region = $builder->build([
            'loader' => [
                'yaml' => $yamlContent,
                'yamlHelpers' => $helpers,
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
        $result = $context['result'];
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
