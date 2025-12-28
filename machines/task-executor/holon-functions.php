<?php

declare(strict_types=1);

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use Noem\State\StandardRuntime;
use Noem\State\RuntimeConfig;

// Module-level functions for Task Executor machine

function onEnterIdle()
{
    return function (object $t): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "   TASK EXECUTOR - AI Assistant\n";
        echo "   Powered by Noem State Machine + Claude AI\n";
        echo str_repeat('=', 60) . "\n\n";
        echo "I can chat or execute tasks for you!\n";
        echo "- Just chat naturally for conversation\n";
        echo "- Describe a task to have me execute it as a state machine\n";
        echo "Type 'exit' or 'quit' to end.\n\n";

        $this->set('conversation_history', []);
        $this->set('user_input', null);
        $this->set('input_type', null);  // 'conversation' or 'task'
        $this->set('ai_response', null);
        $this->set('task_definition', null);
        $this->set('task_events', []);
        $this->set('task_runtime', null);
        $this->set('task_summary', null);
        $this->set('should_exit', false);
    };
}

function onEnterWaitingForInput()
{
    return function (object $t): void {
        echo "\n> ";
    };
}

function actionWaitingForInput()
{
    return function (object $t): Generator {
        $input = fgets(STDIN);

        if ($input !== false) {
            $userInput = trim($input);

            if (!empty($userInput)) {
                if (in_array(strtolower($userInput), ['exit', 'quit', 'bye'])) {
                    $this->set('should_exit', true);
                    $this->set('user_input', $userInput);
                    return;
                }

                $this->set('user_input', $userInput);

                $history = $this->get('conversation_history');
                $history[] = ['role' => 'user', 'content' => $userInput];
                $this->set('conversation_history', $history);

                return;
            }
        }

        yield;
    };
}

function guardHasUserInput()
{
    return function (object $t): bool {
        return $this->get('user_input') !== null;
    };
}

// Classification State

function onEnterClassifyInput()
{
    return function (object $t): void {
        echo "\n[Analyzing input...]\n";
    };
}

function actionClassifyInput()
{
    return function (object $t): Generator {
        $userInput = $this->get('user_input');

        $schema = [
            'type' => 'object',
            'required' => ['classification', 'confidence'],
            'properties' => [
                'classification' => [
                    'type' => 'string',
                    'enum' => ['conversation', 'task'],
                    'description' => 'The classification of the user input'
                ],
                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                    'description' => 'Confidence score between 0 and 1'
                ]
            ]
        ];

        $prompt = <<<PROMPT
Classify the following user input as either "conversation" or "task".

User Input: {$userInput}

Rules:
- "conversation": Simple questions, greetings, chitchat, requests for information
- "task": Requests to perform multi-step operations, execute workflows, process data, automate something

Examples of "conversation":
- "Hello, how are you?"
- "What's the weather like?"
- "Tell me about state machines"
- "Can you explain async programming?"

Examples of "task":
- "Create a file processing workflow"
- "Build a data validation pipeline"
- "Execute a multi-step deployment"
- "Process these files in sequence"

Return a JSON object with:
- classification: either "conversation" or "task"
- confidence: a number between 0 and 1 indicating your confidence
PROMPT;

        $result = $this->capture($prompt, $schema, 'ollama');

        $this->set('input_type', $result['classification']);
        $this->set('classification_confidence', $result['confidence']);

        echo "[Type: {$result['classification']} (confidence: {$result['confidence']})]\n";

        yield;
    };
}

function guardIsConversation()
{
    return function (object $t): bool {
        return $this->get('input_type') === 'conversation';
    };
}

function guardIsTask()
{
    return function (object $t): bool {
        return $this->get('input_type') === 'task';
    };
}

// Conversation Response State

function onEnterRespondConversation()
{
    return function (object $t): void {
        echo "\nAssistant: ";
        flush();
    };
}

function actionRespondConversation()
{
    return function (object $t): Generator {
        $history = $this->get('conversation_history');

        $conversationContext = "";
        foreach ($history as $entry) {
            $role = ucfirst($entry['role']);
            $conversationContext .= "$role: {$entry['content']}\n";
        }

        $this->set('conversationContext', $conversationContext);

        $template = $this->template(
            <<<'TEMPLATE'
{{#complete temperature=0.7 max=500 backend="ollama"}}
You are a helpful, friendly AI assistant.

Conversation History:
{{conversationContext}}

Instructions:
- Provide helpful, concise, and friendly responses
- Keep responses relatively brief (2-4 sentences typically)
- Be conversational and natural
- Don't use markdown formatting
- Don't start with "Assistant:" or similar prefixes
{{/complete}}
TEMPLATE
        );

        assert($template instanceof Generator);

        $fullResponse = '';
        while ($template->valid()) {
            $chunk = $template->current();
            $fullResponse .= $chunk;
            echo $chunk;
            flush();
            $template->next();
            yield;
        }

        $this->set('ai_response', $fullResponse);

        $history[] = ['role' => 'assistant', 'content' => $fullResponse];
        $this->set('conversation_history', $history);

        echo "\n";
    };
}

function guardResponseComplete()
{
    return function (object $t): bool {
        return $this->get('ai_response') !== null;
    };
}

// Task Generation State

function onEnterTaskGeneration()
{
    return function (object $t): void {
        echo "\n[Generating task state machine...]\n";
    };
}

function actionTaskGeneration()
{
    return function (object $t): Generator {
        $userInput = $this->get('user_input');

        // Load the JSON schema
        $schemaPath = '/var/www/html/machines/task-executor/region-schema.json';
        $schema = json_decode(file_get_contents($schemaPath), true);

        // Yield to allow async processing
        yield;

        $prompt = <<<PROMPT
Generate a RegionLoader-compatible state machine definition for the following task:

User Request: {$userInput}

Requirements:
1. Create states that represent distinct phases of the task
2. Use descriptive state names (e.g., "initialize", "process_data", "validate", "complete")
3. Define clear transitions between states
4. Include an initial state and a final state
5. Keep the structure simple - just states, transitions, initial, and final
6. DO NOT include any callbacks or PHP code

Return a JSON object with:
- states: array of state objects with name and optional transitions
- initial: name of the starting state
- final: name of the ending state

Example structure:
{
  "states": [
    {"name": "start", "transitions": [{"target": "process"}]},
    {"name": "process", "transitions": [{"target": "finish"}]},
    {"name": "finish"}
  ],
  "initial": "start",
  "final": "finish"
}
PROMPT;

        $result = $this->capture($prompt, $schema, 'ollama');

        // Convert JSON result to YAML for RegionLoader
        $taskYaml = "states:\n";
        foreach ($result['states'] as $state) {
            $taskYaml .= "  - name: {$state['name']}\n";
            if (isset($state['transitions']) && !empty($state['transitions'])) {
                $taskYaml .= "    transitions:\n";
                foreach ($state['transitions'] as $transition) {
                    $taskYaml .= "      - target: {$transition['target']}\n";
                }
            }
            $taskYaml .= "\n";
        }
        $taskYaml .= "initial: {$result['initial']}\n";
        $taskYaml .= "final: {$result['final']}\n";

        $this->set('task_definition', $taskYaml);
        $this->set('task_structure', $result);

        echo "[Task state machine generated]\n";
        echo "[States: " . count($result['states']) . "]\n";

        yield;
    };
}

function guardHasTaskDefinition()
{
    return function (object $t): bool {
        return $this->get('task_definition') !== null;
    };
}

// Task Execution State

function onEnterExecuteTask()
{
    return function (object $t): void {
        echo "\n" . str_repeat('-', 60) . "\n";
        echo "EXECUTING TASK\n";
        echo str_repeat('-', 60) . "\n";
    };
}

function actionExecuteTask()
{
    return function (object $t): Generator {
        $taskYaml = $this->get('task_definition');

        try {
            // Build the task region
            $builder = new RegionBuilder();
            $builder->enableFeatures(new \Noem\State\Feature\ExtendedState\ExtendedState());
            $builder->enableFeatures(new \Noem\State\Feature\Loader\RegionLoader());

            $taskRegion = $builder->build([
                'loader' => [
                    'yaml' => $taskYaml,
                ],
            ]);

            // Create runtime for task execution
            $taskRuntime = new StandardRuntime(
                $taskRegion,
                new RuntimeConfig(maxIterations: 100)  // Limit task iterations
            );

            $this->set('task_runtime', $taskRuntime);

            // Subscribe to events
            $eventLog = [];
            $taskRegion->on(function ($event) use (&$eventLog) {
                $eventLog[] = $event;

                // Display progress
                if (property_exists($event, 'state') && property_exists($event, 'name')) {
                    echo "[State: {$event->name}]\n";
                }
            });

            // Execute the task runtime
            echo "\n";
            $taskRuntime->run();  // Run to completion
            echo "\n";

            $this->set('task_events', $eventLog);
            $this->set('task_complete', true);

            echo str_repeat('-', 60) . "\n";
            echo "TASK EXECUTION COMPLETE\n";
            echo str_repeat('-', 60) . "\n";

        } catch (\Throwable $e) {
            echo "\n[ERROR during task execution: {$e->getMessage()}]\n";
            echo "[File: {$e->getFile()}:{$e->getLine()}]\n";
            $this->set('task_error', $e->getMessage());
            $this->set('task_complete', true);
        }

        yield;
    };
}

function guardTaskComplete()
{
    return function (object $t): bool {
        return $this->get('task_complete') === true;
    };
}

// Task Summary State

function onEnterTaskSummary()
{
    return function (object $t): void {
        echo "\n[Generating task summary...]\n";
    };
}

function actionTaskSummary()
{
    return function (object $t): Generator {
        $userInput = $this->get('user_input');
        $taskEvents = $this->get('task_events');
        $taskError = $this->get('task_error');

        $eventSummary = "Total events: " . count($taskEvents);
        if ($taskError) {
            $eventSummary .= "\nError occurred: $taskError";
        }

        $this->set('userInput', $userInput);
        $this->set('eventSummary', $eventSummary);

        $template = $this->template(
            <<<'TEMPLATE'
{{#complete temperature=0.7 max=300 backend="anthropic"}}
Provide a brief summary of the task execution.

Original Task: {{userInput}}
Execution Summary: {{eventSummary}}

Generate a 1-2 sentence summary explaining what was executed and the result.
Don't use markdown formatting.
{{/complete}}
TEMPLATE
        );

        assert($template instanceof Generator);

        echo "\nAssistant: ";
        $summary = '';
        while ($template->valid()) {
            $chunk = $template->current();
            $summary .= $chunk;
            echo $chunk;
            flush();
            $template->next();
            yield;
        }

        echo "\n";

        $this->set('task_summary', $summary);

        $history = $this->get('conversation_history');
        $history[] = ['role' => 'assistant', 'content' => "Task executed: $summary"];
        $this->set('conversation_history', $history);
    };
}

function guardSummaryComplete()
{
    return function (object $t): bool {
        return $this->get('task_summary') !== null;
    };
}

// Cleanup State

function onEnterCleanup()
{
    return function (object $t): void {
        // Clear temporary state
        $this->set('user_input', null);
        $this->set('input_type', null);
        $this->set('ai_response', null);
        $this->set('task_definition', null);
        $this->set('task_events', []);
        $this->set('task_runtime', null);
        $this->set('task_summary', null);
        $this->set('task_complete', null);
        $this->set('task_error', null);
    };
}

function actionCleanup()
{
    return function (object $t): Generator {
        usleep(50000);  // Brief pause
        yield;
    };
}

function guardNotExiting()
{
    return function (object $t): bool {
        return !$this->get('should_exit');
    };
}

function guardExiting()
{
    return function (object $t): bool {
        return $this->get('should_exit') === true;
    };
}

// Finished State

function onEnterFinished()
{
    return function (object $t): void {
        $history = $this->get('conversation_history');
        $messageCount = count($history);

        echo "\n" . str_repeat('-', 60) . "\n";
        echo "Session ended.\n";
        echo "Total interactions: $messageCount\n";
        echo "Thank you for using Task Executor!\n";
        echo str_repeat('-', 60) . "\n\n";
    };
}
