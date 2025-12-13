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
 * Acceptance Criterion: YAML machine with mixed sync/async callbacks works
 */
#[Group('async'), Group('yaml-callback-extensions')]
class MixedCallbackTypesTest extends TestCase
{
    public function testYamlMachineWithMixedSyncAsyncCallbacksWorks(): void
    {
        $yaml = <<<YAML
states:
  - name: idle
    action:
      - run: !php |
          return function(object \$trigger) {
            \$trigger->log[] = 'sync-action';
          };
      - run: !php |
          return function(object \$trigger) {
            \$trigger->log[] = 'async-start';
            yield;
            \$trigger->log[] = 'async-end';
          };
        async:
          priority: low
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

        $trigger = (object)['log' => []];

        // First trigger: sync executes immediately, async starts
        $region->trigger($trigger);
        $this->assertContains('sync-action', $trigger->log);
        $this->assertContains('async-start', $trigger->log);
        $this->assertNotContains('async-end', $trigger->log);

        // Second trigger: sync executes again, async finishes
        $region->trigger($trigger);
        $this->assertContains('async-end', $trigger->log);
    }
}
