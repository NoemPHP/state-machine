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
 * Acceptance Criterion: YAML machine with async actions executes correctly
 */
#[Group('async'), Group('yaml-callback-extensions')]
class AsyncYamlMachineTest extends TestCase
{
    public function testYamlMachineWithAsyncActionsExecutesCorrectly(): void
    {
        $yaml = <<<YAML
states:
  - name: idle
    action:
      - run: !php |
          return function(object \$trigger) {
            \$trigger->executed[] = 'step1';
            yield;
            \$trigger->executed[] = 'step2';
            yield;
            \$trigger->executed[] = 'step3';
          };
        async:
          priority: low
          singleton: true
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

        // Trigger multiple times to advance async task
        $trigger = (object)['executed' => []];

        $region->trigger($trigger);
        $this->assertEquals(['step1'], $trigger->executed);

        $region->trigger($trigger);
        $this->assertEquals(['step1', 'step2'], $trigger->executed);

        $region->trigger($trigger);
        $this->assertEquals(['step1', 'step2', 'step3'], $trigger->executed);
    }
}
