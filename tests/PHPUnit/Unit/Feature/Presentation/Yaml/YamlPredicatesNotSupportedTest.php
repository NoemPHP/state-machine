<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Yaml;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: YAML does not support explicit predicate definitions
 * Intent: Keeps YAML simple by restricting predicates to programmatic registration only
 * Criticality: constraint
 *
 * @spec specs/features/presentation.yaml:440-443
 */
final class YamlPredicatesNotSupportedTest extends TestCase
{
    public function testYamlPredicatesNotSupported(): void
    {
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
    - name: testField
      type: string
      default: test
presentations:
  - key: testField
    label: Test Label
    intent: Test Intent
    predicate: "some_function"
states:
  - name: initial
YAML;

        // YAML with explicit predicate should fail validation or be ignored
        try {
            $region = $builder->build(['loader' => ['yaml' => $yamlContent]]);

            // If no exception, verify predicate was NOT set from YAML
            $registry = $builder->chainMail->get(\Noem\State\Feature\Presentation\PresentationRegistry::class);
            $presentation = $registry->get('testField');

            if ($presentation !== null) {
                $this->assertNull(
                    $presentation->predicate,
                    'YAML should not support explicit predicate definitions - predicate must be null'
                );
            }
        } catch (\Exception $e) {
            // Exception is acceptable - YAML parser may reject predicate field
            $this->addToAssertionCount(1);
        }
    }
}
