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
 * Acceptance Criteria: Complete workflow from YAML presentation declaration to enumeration
 * Intent: Validates entire registration, schema validation, and discovery cycle
 * Criticality: integration
 */
#[CoversClass(PresentationFeature::class)]
class YamlToEnumerationWorkflowTest extends TestCase
{
    public function testCompleteYamlToEnumerationWorkflow(): void
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
    - name: userName
      type: string
      default: Alice
    - name: userAge
      type: integer
      default: 30
    - name: isActive
      type: boolean
      default: true
presentations:
  - key: userName
    label: User Name
    intent: The name of the current user
  - key: userAge
    label: User Age
    intent: The age of the current user
states:
  - name: initial
    transitions:
      - event: enumerate
        target: enumerating
  - name: enumerating
    onEnter: |
      # Enumerate presentations
      $this->abilities('enumerate-presentations')->then(function($response) {
          $this->set('enumerationResult', $response->parameters);
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

        $runtime->trigger('enumerate');
        $runtime->run();

        $result = $region->get('enumerationResult');

        // Verify complete workflow
        $this->assertIsArray($result);
        $this->assertArrayHasKey('presentations', $result);

        $presentations = $result['presentations'];
        $this->assertCount(2, $presentations);

        // Verify presentations have complete structure
        foreach ($presentations as $presentation) {
            $this->assertArrayHasKey('key', $presentation);
            $this->assertArrayHasKey('label', $presentation);
            $this->assertArrayHasKey('intent', $presentation);
            $this->assertArrayHasKey('schema', $presentation);

            // Schema should be populated from JsonSchemaFeature
            $this->assertIsArray($presentation['schema']);
            $this->assertArrayHasKey('type', $presentation['schema']);
        }

        // Verify specific presentations
        $keys = array_column($presentations, 'key');
        $this->assertContains('userName', $keys);
        $this->assertContains('userAge', $keys);
    }
}
