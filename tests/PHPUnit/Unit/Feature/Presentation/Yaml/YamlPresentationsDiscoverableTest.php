<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Yaml;

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
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: YAML-registered presentations discoverable via enumerate-presentations ability
 * Intent: Validates YAML presentations participate in full discovery infrastructure
 * Criticality: contract
 *
 * @spec specs/features/presentation.yaml:450-453
 */
final class YamlPresentationsDiscoverableTest extends TestCase
{
    public function testYamlPresentationsDiscoverable(): void
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
    - name: yamlField
      type: string
      default: test
presentations:
  - key: yamlField
    label: YAML Label
    intent: YAML Intent
states:
  - name: initial
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !php |
          return function(object $trigger) {
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

        // Build region with YAML presentations
        $region = $builder->build(['loader' => ['yaml' => $yamlContent, 'yamlHelpers' => $helpers]]);

        $runtime = new StandardRuntime($region);
        $runtime->run();

        // Access context through Meta chain
        $meta = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metaParams = new \Noem\State\Chains\Params\Meta($region, \Noem\State\Feature\ExtendedState\ContextMetaType::get());
        $context = $meta->call($metaParams);
        $result = $context['result'];

        $this->assertNotNull($result, 'enumerate-presentations ability should return result');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('presentations', $result);
        $this->assertIsArray($result['presentations']);

        // Find the YAML-registered presentation
        $found = false;
        foreach ($result['presentations'] as $presentation) {
            if ($presentation['key'] === 'yamlField') {
                $found = true;
                $this->assertSame('YAML Label', $presentation['label']);
                $this->assertSame('YAML Intent', $presentation['intent']);
                break;
            }
        }

        $this->assertTrue($found, 'YAML-registered presentation must be discoverable via enumerate-presentations');
    }
}
