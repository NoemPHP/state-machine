<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\Loader;

use Noem\State\Feature\Loader\Machine;
use Noem\State\Feature\Transitions\TransitionsFeature;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Machine subclass executes complete state machine from YAML
 */
#[Group('loader')]
#[Group('integration')]
class MachineExecutionTest extends TestCase
{
    public function testMachineExecutesCompleteWorkflow(): void
    {
        // Track execution for verification
        $GLOBALS['machine_execution_log'] = [];
        
        $machine = new class extends Machine {
            private int $triggerCount = 0;
            
            public function yaml(): string
            {
                return <<<'YAML'
                states:
                  - name: start
                    transitions:
                      - target: middle
                        guard: !php "return fn(object $t): bool => $t->step === 1;"
                    action:
                      - run: !php "return function(object $t) { $GLOBALS['machine_execution_log'][] = 'start'; return 'start'; };"
                  - name: middle
                    transitions:
                      - target: end
                        guard: !php "return fn(object $t): bool => $t->step === 2;"
                    action:
                      - run: !php "return function(object $t) { $GLOBALS['machine_execution_log'][] = 'middle'; return 'middle'; };"
                  - name: end
                    onEnter:
                      - run: !php "return function(object $t) { $GLOBALS['machine_execution_log'][] = 'end'; return 'end'; };"
                initial: start
                final: end
                YAML;
            }
            
            public function trigger(): object
            {
                $trigger = new stdClass();
                $trigger->step = ++$this->triggerCount;
                return $trigger;
            }
            
            public function features(): iterable
            {
                return [
                    ...parent::features(),
                    new TransitionsFeature(),
                ];
            }
        };
        
        // Test using static run method
        $result = Machine::run($machine);
        
        // Verify execution happened in correct order
        $this->assertEquals(['start', 'middle', 'end'], $GLOBALS['machine_execution_log']);
        
        // Verify result is the last trigger result
        $this->assertInstanceOf(stdClass::class, $result);
        
        // Cleanup
        unset($GLOBALS['machine_execution_log']);
    }
    
    public function testMachineExecuteMethod(): void
    {
        $GLOBALS['execute_test_log'] = [];
        
        $machine = new class extends Machine {
            private int $step = 0;
            
            public function yaml(): string
            {
                return <<<'YAML'
                states:
                  - name: a
                    transitions:
                      - target: b
                        guard: !php "return fn(object $t): bool => true;"
                    action:
                      - run: !php "return function(object $t) { $GLOBALS['execute_test_log'][] = 'a'; return 'a'; };"
                  - name: b
                    onEnter:
                      - run: !php "return function(object $t) { $GLOBALS['execute_test_log'][] = 'b'; return 'b'; };"
                initial: a
                final: b
                YAML;
            }
            
            public function trigger(): object
            {
                return new stdClass();
            }
        };
        
        // Build region manually
        $builder = new \Noem\State\RegionBuilder();
        $region = $builder
            ->enableFeatures(...$machine->features())
            ->build($machine->builderArgs());
        
        // Test execute method
        $result = $machine->execute($region);
        
        $this->assertEquals(['a', 'b'], $GLOBALS['execute_test_log']);
        $this->assertInstanceOf(stdClass::class, $result);
        
        unset($GLOBALS['execute_test_log']);
    }
    
    public function testMachineWithContainerDependencies(): void
    {
        $GLOBALS['container_test_log'] = [];
        
        $machine = new class extends Machine {
            public function yaml(): string
            {
                return <<<'YAML'
                states:
                  - name: start
                    transitions:
                      - target: end
                    action:
                      - run: !php "return function(object $t) { $GLOBALS['container_test_log'][] = 'start'; return 'start'; };"
                  - name: end
                    onEnter:
                      - run: !get testCallback
                initial: start
                final: end
                YAML;
            }
            
            public function trigger(): object
            {
                return new stdClass();
            }
            
            public function container(): iterable
            {
                return [
                    'testCallback' => function(object $t) {
                        $GLOBALS['container_test_log'][] = 'from-container';
                        return 'end';
                    },
                ];
            }
        };
        
        Machine::run($machine);
        
        $this->assertEquals(['start', 'from-container'], $GLOBALS['container_test_log']);
        
        unset($GLOBALS['container_test_log']);
    }
    
    public function testMachineWithComplexTransitions(): void
    {
        $GLOBALS['complex_log'] = [];
        
        $machine = new class extends Machine {
            private int $counter = 0;
            
            public function yaml(): string
            {
                return <<<'YAML'
                states:
                  - name: idle
                    transitions:
                      - target: processing
                        guard: !php "return fn(object $t): bool => $t->count > 0;"
                    action:
                      - run: !php "return function(object $t) { $GLOBALS['complex_log'][] = 'idle-'.$t->count; return 'idle'; };"
                  - name: processing
                    transitions:
                      - target: done
                        guard: !php "return fn(object $t): bool => $t->count >= 3;"
                    action:
                      - run: !php "return function(object $t) { $GLOBALS['complex_log'][] = 'processing-'.$t->count; return 'processing'; };"
                  - name: done
                    onEnter:
                      - run: !php "return function(object $t) { $GLOBALS['complex_log'][] = 'done'; return 'done'; };"
                initial: idle
                final: done
                YAML;
            }
            
            public function trigger(): object
            {
                $trigger = new stdClass();
                $trigger->count = ++$this->counter;
                return $trigger;
            }
        };
        
        Machine::run($machine);
        
        // Should trigger multiple times: 
        // idle-1 (transition to processing), processing-2, processing-3 (transition to done), done
        $this->assertContains('idle-1', $GLOBALS['complex_log']);
        $this->assertContains('processing-2', $GLOBALS['complex_log']);
        $this->assertContains('processing-3', $GLOBALS['complex_log']);
        $this->assertContains('done', $GLOBALS['complex_log']);
        
        unset($GLOBALS['complex_log']);
    }
}
