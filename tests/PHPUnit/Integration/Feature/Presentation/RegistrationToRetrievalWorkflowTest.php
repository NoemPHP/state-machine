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
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Complete workflow from programmatic registration to state retrieval
 * Intent: Validates registration, predicate evaluation, and value retrieval cycle
 * Criticality: integration
 */
#[CoversClass(PresentationFeature::class)]
class RegistrationToRetrievalWorkflowTest extends TestCase
{
    public function testCompleteRegistrationToRetrievalWorkflow(): void
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
              # Register presentations programmatically
              $this->presentation('visibleField', 'Visible', 'Should be visible', null, fn($r) => true);
              $this->presentation('hiddenField', 'Hidden', 'Should be hidden', null, fn($r) => false);
              $this->presentation('unconditionalField', 'Unconditional', 'Always visible');

              # Get presented state
              $this->abilities('get-presented-state')->then(function($response) {
                  $this->set('stateResult', $response->parameters);
              });
              yield;
          };
    transitions:
      - target: final
  - name: final
YAML;

        $helpers = ['php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper()];
        $schema = [
            'visibleField' => ['name' => 'visibleField', 'type' => 'string', 'default' => 'visible-value'],
            'hiddenField' => ['name' => 'hiddenField', 'type' => 'string', 'default' => 'hidden-value'],
            'unconditionalField' => ['name' => 'unconditionalField', 'type' => 'integer', 'default' => 42],
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
        $result = $context['stateResult'];

        // Verify complete workflow
        $this->assertIsArray($result);
        $this->assertArrayHasKey('values', $result);

        $values = $result['values'];

        // Visible field should be included
        $this->assertArrayHasKey('visibleField', $values);
        $this->assertSame('visible-value', $values['visibleField']['value']);
        $this->assertSame('Visible', $values['visibleField']['label']);

        // Hidden field should be excluded (predicate false)
        $this->assertArrayNotHasKey('hiddenField', $values);

        // Unconditional field should be included
        $this->assertArrayHasKey('unconditionalField', $values);
        $this->assertSame(42, $values['unconditionalField']['value']);
        $this->assertSame('Unconditional', $values['unconditionalField']['label']);
    }
}
