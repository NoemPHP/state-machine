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
use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\SchemaNotFoundException;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Complete workflow demonstrating schema validation enforcement
 * Intent: Validates schema requirement catching unvalidated field exposure attempts
 * Criticality: integration
 */
#[CoversClass(PresentationFeature::class)]
class SchemaValidationWorkflowTest extends TestCase
{
    public function testSchemaValidationEnforcedDuringBuilderRegistration(): void
    {
        $this->markTestSkipped('YAML presentations support not yet implemented.');

        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage('undefinedField');

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
    - name: validField
      type: string
      default: test
presentations:
  - key: undefinedField
    label: Undefined Field
    intent: This should fail validation
states:
  - name: initial
YAML;

        // Should throw SchemaNotFoundException during build
        $builder->build(['loader' => ['yaml' => $yamlContent]]);
    }

    public function testSchemaValidationEnforcedDuringRuntimeRegistration(): void
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
              try {
                  # Attempt to register presentation without schema
                  $this->presentation('invalidField', 'Invalid', 'Should fail');
                  $this->set('error', 'No exception thrown');
              } catch (\Noem\State\Feature\Presentation\SchemaNotFoundException $e) {
                  $this->set('error', 'SchemaNotFoundException');
                  $this->set('errorMessage', $e->getMessage());
              }
          };
    transitions:
      - target: final
  - name: final
YAML;

        $helpers = ['php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper()];
        $schema = [
            'validField' => ['name' => 'validField', 'type' => 'string', 'default' => 'test'],
        ];

        // Add JsonSchema build step to initialize defaults
        $builder->addBuildStep(new \Noem\State\Feature\JsonSchema\AddJsonSchema(array_values($schema)));

        $region = $builder->build([
            'loader' => [
                'yaml' => $yamlContent,
                'yamlHelpers' => $helpers,
            ]
        ]);

        // Manually populate schemas in PresentationRegistry - only validField, not invalidField
        $presentationRegistry = $builder->chainMail->get(PresentationRegistry::class);
        $presentationRegistry->setSchemas($schema);

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        // Access context through Meta chain since Region doesn't have get() method
        $meta = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metaParams = new \Noem\State\Chains\Params\Meta($region, \Noem\State\Feature\ExtendedState\ContextMetaType::get());
        $context = $meta->call($metaParams);

        // Verify exception was thrown
        $this->assertSame('SchemaNotFoundException', $context['error']);
        $this->assertStringContainsString('invalidField', $context['errorMessage']);
    }

    public function testValidSchemaAllowsRegistration(): void
    {
        $this->markTestSkipped('YAML presentations support not yet implemented.');

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
    - name: validField
      type: string
      default: test-value
presentations:
  - key: validField
    label: Valid Field
    intent: This should succeed
states:
  - name: initial
YAML;

        // Should succeed without throwing
        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);

        // Verify presentation was registered
        $registry = $builder->chainMail->get(PresentationRegistry::class);
        $presentation = $registry->get('validField');

        $this->assertNotNull($presentation);
        $this->assertSame('validField', $presentation->key);
        $this->assertSame('Valid Field', $presentation->label);
    }

    public function testSchemaProvidedInEnumeration(): void
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
    - name: typedField
      type: integer
      default: 123
      minimum: 0
      maximum: 999
presentations:
  - key: typedField
    label: Typed Field
    intent: Field with validation constraints
states:
  - name: initial
    transitions:
      - event: enumerate
        target: enumerating
  - name: enumerating
    onEnter: |
      $this->abilities('enumerate-presentations')->then(function($response) {
          $this->set('result', $response->parameters);
          $this->trigger('done');
      });
      yield;
    transitions:
      - event: done
        target: final
  - name: final
YAML;

        $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);
        $runtime = new \Noem\State\Runtime($region);

        $runtime->trigger('enumerate');
        $runtime->run();

        $result = $region->get('result');
        $presentations = $result['presentations'];

        // Verify schema is included in enumeration
        $this->assertCount(1, $presentations);
        $presentation = $presentations[0];

        $this->assertArrayHasKey('schema', $presentation);
        $this->assertSame('integer', $presentation['schema']['type']);
        $this->assertSame(0, $presentation['schema']['minimum']);
        $this->assertSame(999, $presentation['schema']['maximum']);
    }
}
