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
              # Register presentations
              $this->presentation('userCount', 'Active Users', 'Current user count');
              $this->presentation('status', 'Status', 'System status');

              # Call enumerate-presentations ability
              $this->abilities('enumerate-presentations')->then(function ($response) {
                  $this->set('enumerateResult', $response->parameters);
              });
              yield;
          };
    transitions:
      - target: final
  - name: final
YAML;

        $helpers = ['php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper()];
        $schema = [
            'userCount' => ['name' => 'userCount', 'type' => 'integer', 'default' => 0],
            'status' => ['name' => 'status', 'type' => 'string', 'default' => 'idle'],
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
        $result = $context['enumerateResult'];

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
          };
    transitions:
      - target: final
  - name: final
YAML;

        $helpers = ['php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper()];
        $schema = [
            'visible' => ['name' => 'visible', 'type' => 'string', 'default' => 'test'],
            'hidden' => ['name' => 'hidden', 'type' => 'string', 'default' => 'test'],
            'nullPred' => ['name' => 'nullPred', 'type' => 'string', 'default' => 'test'],
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
              $this->abilities('enumerate-presentations')->then(function($response) {
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
              $callbackExecuted = false;
              $this->abilities('enumerate-presentations')->then(function($response) use (&$callbackExecuted) {
                  $callbackExecuted = true;
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
}
