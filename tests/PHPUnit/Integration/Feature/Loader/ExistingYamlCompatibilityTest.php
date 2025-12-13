<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Loader;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Existing YAML machines continue working without modification
 */
#[Group('async'), Group('yaml-callback-extensions')]
class ExistingYamlCompatibilityTest extends TestCase
{
    public function testExistingYamlMachinesContinueWorkingWithoutModification(): void
    {
        // Legacy YAML without any async configuration
        $yaml = <<<YAML
states:
  - name: idle
    action:
      - run: !php |
          return function(object \$trigger) {
            \$trigger->executed = true;
          };
initial: idle
YAML;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new RegionLoader(),
            new ExtendedState(),
            new AsyncFeature()
        );

        $helpers = [
            'php' => new PhpEvalHelper(),
        ];

        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);

        $trigger = (object)['executed' => false];
        $region->trigger($trigger);

        $this->assertTrue($trigger->executed, 'Legacy YAML callbacks should execute immediately');
    }

    public function testLegacyYamlWithComplexStructure(): void
    {
        $yaml = <<<YAML
states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$trigger) {
            if (!isset(\$trigger->log)) {
              \$trigger->log = [];
            }
            \$trigger->log[] = 'entered-idle';
          };
    onExit:
      - run: !php |
          return function(object \$trigger) {
            if (!isset(\$trigger->log)) {
              \$trigger->log = [];
            }
            \$trigger->log[] = 'exited-idle';
          };
    transitions:
      - target: active
  - name: active
    onEnter:
      - run: !php |
          return function(object \$trigger) {
            if (!isset(\$trigger->log)) {
              \$trigger->log = [];
            }
            \$trigger->log[] = 'entered-active';
          };
initial: idle
YAML;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new RegionLoader(),
            new ExtendedState(),
            new \Noem\State\Feature\Transitions\TransitionsFeature(),
            new AsyncFeature()
        );

        $helpers = [
            'php' => new PhpEvalHelper(),
        ];

        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);

        // Transition to active - this should trigger onExit for idle and onEnter for active
        $trigger = (object)['log' => []];
        $region->trigger($trigger);

        $this->assertContains('exited-idle', $trigger->log);
        $this->assertContains('entered-active', $trigger->log);
    }
}
