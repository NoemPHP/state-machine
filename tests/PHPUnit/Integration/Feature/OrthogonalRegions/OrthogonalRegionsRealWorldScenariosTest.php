<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\OrthogonalRegions;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use Noem\State\RuntimeConfig;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OrthogonalRegions handles real-world scenarios like task orchestration
 */
#[Group('orthogonal-regions')]
#[Group('integration')]
#[Group('real-world')]
class OrthogonalRegionsRealWorldScenariosTest extends TestCase
{
    public function testTaskOrchestratorWithDynamicWorkers(): void
    {
        $tasks = ['task1', 'task2', 'task3'];
        $completedTasks = [];

        $createWorker = function ($taskName) {
            return (new RegionBuilder())
                ->setStates('working', 'done')
                ->markInitial('working')
                ->markFinal('done')
                ->onEnter('working', function (object $t) use ($taskName) {
                    $this->set("task_{$taskName}_status", 'started');
                })
                ->onAction('working', function (object $t) use ($taskName) {
                    $this->set("task_{$taskName}_status", 'completed');
                    return 'done';
                });
        };

        $orchestrator = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('orchestrating', 'done')
            ->markInitial('orchestrating')
            ->markFinal('done')
            ->onEnter('orchestrating', function (object $t) use ($tasks) {
                $this->set('tasks', $tasks);
            })
            ->onAction('orchestrating', function (object $t) use ($createWorker, &$completedTasks) {
                $tasks = $this->get('tasks');

                foreach ($tasks as $task) {
                    $worker = $createWorker($task);
                    $runtime = $this->summon($worker);
                    $runtime->run();

                    if ($this->get("task_{$task}_status") === 'completed') {
                        $completedTasks[] = $task;
                    }
                }

                return 'done';
            })
            ->build();

        $runtime = new StandardRuntime($orchestrator);
        $runtime->run();

        $this->assertEquals($tasks, $completedTasks);
        $this->assertTrue($runtime->isComplete());
    }

    public function testParallelDataProcessingPipeline(): void
    {
        $processedData = [];

        $validator = (new RegionBuilder())
            ->setStates('validating', 'done')
            ->markInitial('validating')
            ->markFinal('done')
            ->onEnter('validating', function (object $t) {
                $data = $this->get('input_data');
                $this->set('validation_result', strlen($data) > 0);
            })
            ->onAction('validating', fn(object $t) => 'done');

        $transformer = (new RegionBuilder())
            ->setStates('transforming', 'done')
            ->markInitial('transforming')
            ->markFinal('done')
            ->onEnter('transforming', function (object $t) {
                $data = $this->get('input_data');
                $this->set('transformed_data', strtoupper($data));
            })
            ->onAction('transforming', fn(object $t) => 'done');

        $pipeline = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$validator, $transformer]
        ))
            ->setStates('pipeline', 'collecting', 'done')
            ->markInitial('pipeline')
            ->markFinal('done')
            ->onEnter('pipeline', function (object $t) {
                $this->set('input_data', 'hello world');
            })
            ->onAction('pipeline', fn(object $t) => 'collecting')
            ->onEnter('collecting', function (object $t) use (&$processedData) {
                $processedData['valid'] = $this->get('validation_result');
                $processedData['transformed'] = $this->get('transformed_data');
            })
            ->onAction('collecting', fn(object $t) => 'done')
            ->build();

        $runtime = new StandardRuntime($pipeline);
        $runtime->run();

        $this->assertTrue($processedData['valid']);
        $this->assertEquals('HELLO WORLD', $processedData['transformed']);
    }

    public function testHierarchicalStateMachineWithOrthogonalSubsystems(): void
    {
        $systemLog = [];

        // Authentication subsystem
        $auth = (new RegionBuilder())
            ->setStates('checking', 'authenticated')
            ->markInitial('checking')
            ->markFinal('authenticated')
            ->onEnter('checking', function (object $t) use (&$systemLog) {
                $systemLog[] = 'auth_checking';
                $this->set('user_authenticated', true);
            })
            ->onAction('checking', fn(object $t) => 'authenticated');

        // Database subsystem
        $database = (new RegionBuilder())
            ->setStates('connecting', 'connected')
            ->markInitial('connecting')
            ->markFinal('connected')
            ->onEnter('connecting', function (object $t) use (&$systemLog) {
                $systemLog[] = 'db_connecting';
                $this->set('db_connected', true);
            })
            ->onAction('connecting', fn(object $t) => 'connected');

        // Application
        $application = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$auth, $database]
        ))
            ->setStates('starting', 'ready', 'running')
            ->markInitial('starting')
            ->onEnter('starting', function (object $t) use (&$systemLog) {
                $systemLog[] = 'app_starting';
            })
            ->onAction('starting', function (object $t) {
                // Check if all subsystems are ready
                $authReady = $this->get('user_authenticated') ?? false;
                $dbReady = $this->get('db_connected') ?? false;

                return ($authReady && $dbReady) ? 'ready' : 'starting';
            })
            ->onEnter('ready', function (object $t) use (&$systemLog) {
                $systemLog[] = 'app_ready';
            })
            ->build();

        $runtime = new StandardRuntime($application);
        $runtime->run();

        $this->assertContains('auth_checking', $systemLog);
        $this->assertContains('db_connecting', $systemLog);
        $this->assertContains('app_ready', $systemLog);
    }

    public function testDynamicWorkflowWithConditionalSteps(): void
    {
        $workflowSteps = ['step1', 'step2', 'step3'];
        $executedSteps = [];

        $createStep = function ($stepName, $shouldExecute) {
            return (new RegionBuilder())
                ->setStates($stepName, 'done')
                ->markInitial($stepName)
                ->markFinal('done')
                ->onEnter($stepName, function (object $t) use ($stepName, $shouldExecute) {
                    $this->set("execute_{$stepName}", $shouldExecute);
                })
                ->onAction($stepName, fn(object $t) => 'done');
        };

        $workflow = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('executing', 'done')
            ->markInitial('executing')
            ->markFinal('done')
            ->onAction('executing', function (object $t) use ($workflowSteps, $createStep, &$executedSteps) {
                foreach ($workflowSteps as $index => $step) {
                    // Conditionally execute steps
                    $shouldExecute = ($index !== 1); // Skip step2

                    if ($shouldExecute) {
                        $stepBuilder = $createStep($step, true);
                        $this->summon($stepBuilder)->run();
                        $executedSteps[] = $step;
                    }
                }

                return 'done';
            })
            ->build();

        $runtime = new StandardRuntime($workflow);
        $runtime->run();

        $this->assertContains('step1', $executedSteps);
        $this->assertNotContains('step2', $executedSteps);
        $this->assertContains('step3', $executedSteps);
    }

    public function testResourcePoolWithDynamicAllocation(): void
    {
        $allocatedResources = [];
        $maxResources = 3;

        $createResourceConsumer = function ($id) {
            return (new RegionBuilder())
                ->setStates('consuming', 'done')
                ->markInitial('consuming')
                ->markFinal('done')
                ->onEnter('consuming', function (object $t) use ($id) {
                    $allocated = $this->get('allocated_resources') ?? [];
                    $allocated[] = $id;
                    $this->set('allocated_resources', $allocated);
                })
                ->onAction('consuming', fn(object $t) => 'done');
        };

        $pool = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('allocating', 'done')
            ->markInitial('allocating')
            ->markFinal('done')
            ->onEnter('allocating', function (object $t) {
                $this->set('allocated_resources', []);
            })
            ->onAction('allocating', function (object $t) use ($createResourceConsumer, $maxResources, &$allocatedResources) {
                for ($i = 1; $i <= 5; $i++) {
                    $currentCount = count($this->get('allocated_resources'));

                    if ($currentCount < $maxResources) {
                        $consumer = $createResourceConsumer("resource_{$i}");
                        $this->summon($consumer)->run();
                    }
                }

                $allocatedResources = $this->get('allocated_resources');
                return 'done';
            })
            ->build();

        $runtime = new StandardRuntime($pool);
        $runtime->run();

        $this->assertCount($maxResources, $allocatedResources);
        $this->assertEquals(['resource_1', 'resource_2', 'resource_3'], $allocatedResources);
    }

    public function testErrorHandlingInOrthogonalWorkflow(): void
    {
        $errorHandled = false;

        $failingTask = (new RegionBuilder())
            ->setStates('running', 'failed')
            ->markInitial('running')
            ->markFinal('failed')
            ->onEnter('running', function (object $t) {
                $this->set('error', 'task_failed');
            })
            ->onAction('running', fn(object $t) => 'failed');

        $errorHandler = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('monitoring', 'handled')
            ->markInitial('monitoring')
            ->markFinal('handled')
            ->onEnter('monitoring', function (object $t) use ($failingTask) {
                $this->summon($failingTask)->run();
            })
            ->onAction('monitoring', function (object $t) use (&$errorHandled) {
                $error = $this->get('error');
                if ($error) {
                    $errorHandled = true;
                    $this->set('error_handled', true);
                }
                return 'handled';
            })
            ->build();

        $runtime = new StandardRuntime($errorHandler);
        $runtime->run();

        $this->assertTrue($errorHandled);
        $this->assertTrue($errorHandler->context->get('error_handled'));
    }
}
