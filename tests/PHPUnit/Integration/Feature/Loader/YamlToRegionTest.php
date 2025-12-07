<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: RegionLoader builds working region from YAML configuration
 */
#[Group('loader')]
#[Group('integration')]
class YamlToRegionTest extends TestCase
{
    public function testBuildsWorkingRegionFromYaml(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());

        // Use a global array to track callbacks since eval'd closures can't capture test variables
        $GLOBALS['test_logs'] = [
            'enter' => [],
            'exit' => [],
            'action' => [],
        ];

        $yaml = <<<'YAML'
        states:
          - name: idle
            transitions:
              - target: processing
                guard: !php "fn(object $t): bool => $t->startProcessing ?? false"
            onEnter:
              - run: !php "function(object $t) { $GLOBALS['test_logs']['enter'][] = 'idle'; return 'idle'; }"
            onExit:
              - run: !php "function(object $t) { $GLOBALS['test_logs']['exit'][] = 'idle'; }"
            
          - name: processing
            transitions:
              - target: done
                guard: !php "fn(object $t): bool => $t->complete ?? false"
            onEnter:
              - run: !php "function(object $t) { $GLOBALS['test_logs']['enter'][] = 'processing'; return 'processing'; }"
            onExit:
              - run: !php "function(object $t) { $GLOBALS['test_logs']['exit'][] = 'processing'; }"
            action:
              - run: !php "function(object $t) { $GLOBALS['test_logs']['action'][] = 'work'; return 'processing'; }"
              
          - name: done
            onEnter:
              - run: !php "function(object $t) { $GLOBALS['test_logs']['enter'][] = 'done'; return 'done'; }"
            
        initial: idle
        final: done
        YAML;

        $helpers = [
            'php' => fn(string $code) => eval("return $code;"),
        ];

        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);

        // Verify initial state
        $this->assertTrue($region->isInState('idle'), 'Should start in idle state');
        $this->assertFalse($region->isFinal(), 'Should not be in final state initially');
        $this->assertEmpty($GLOBALS['test_logs']['enter'], 'onEnter not called until first trigger');

        // Trigger without guard condition - should stay in idle (this fires initial onEnter)
        $region->trigger(new stdClass());
        $this->assertTrue($region->isInState('idle'), 'Should remain in idle without guard match');
        $this->assertEquals(['idle'], $GLOBALS['test_logs']['enter'], 'Initial onEnter called on first trigger');
        $this->assertCount(0, $GLOBALS['test_logs']['action'], 'No actions should fire in idle state');

        // Transition to processing
        $trigger1 = new stdClass();
        $trigger1->startProcessing = true;
        $region->trigger($trigger1);
        $this->assertTrue($region->isInState('processing'), 'Should transition to processing');
        $this->assertEquals(['idle', 'processing'], $GLOBALS['test_logs']['enter'], 'Should have entered processing');
        $this->assertEquals(['idle'], $GLOBALS['test_logs']['exit'], 'Should have exited idle');

        // Trigger action in processing state
        $region->trigger(new stdClass());
        $this->assertTrue($region->isInState('processing'), 'Should remain in processing');
        $this->assertEquals(['work'], $GLOBALS['test_logs']['action'], 'Action should have been executed');

        // Another action
        $region->trigger(new stdClass());
        $this->assertEquals(['work', 'work'], $GLOBALS['test_logs']['action'], 'Action should execute multiple times');

        // Transition to done
        $trigger2 = new stdClass();
        $trigger2->complete = true;
        $region->trigger($trigger2);
        $this->assertTrue($region->isInState('done'), 'Should transition to done');
        $this->assertTrue($region->isFinal(), 'Should be in final state');
        $this->assertEquals(['idle', 'processing', 'done'], $GLOBALS['test_logs']['enter'], 'Should have entered all states');
        $this->assertEquals(['idle', 'processing'], $GLOBALS['test_logs']['exit'], 'Should have exited non-final states');

        // Cleanup
        unset($GLOBALS['test_logs']);
    }

    public function testLoadsYamlFromFile(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());

        $yamlFile = tempnam(sys_get_temp_dir(), 'test_yaml_');
        $yaml = <<<YAML
        states:
          - name: start
            transitions:
              - target: end
          - name: end
        initial: start
        final: end
        YAML;

        file_put_contents($yamlFile, $yaml);

        try {
            $region = $builder->build([
                'loader' => [
                    'yaml' => $yamlFile,
                ],
            ]);

            $this->assertTrue($region->isInState('start'));
            $region->trigger(new stdClass());
            $this->assertTrue($region->isInState('end'));
            $this->assertTrue($region->isFinal());
        } finally {
            unlink($yamlFile);
        }
    }
}
