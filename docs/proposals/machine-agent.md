# Machine Agent - Detailed Implementation Proposal

**Status**: Refined Specification (Ready for Implementation)
**Type**: Holon Machine
**Date**: 2025-12-28
**Version**: 2.0

---

## Summary

Create a Holon (YAML-based, self-contained machine definition) that creates machines autonomously. The machine:
1. Accepts natural language description of desired machine behavior
2. Clarifies requirements through interactive questioning
3. Assesses confidence level using AI
4. Generates complete Holon YAML + PHP functions
5. Validates and delivers the generated machine

**MVP Scope**: Single-layer machine creation only (no recursion). Focus on machines similar in complexity to task-executor and conversational-cli.

---

## Architecture Overview

### State Machine Flow

```
idle
  ↓ [initialize context]
gathering_requirements
  ↓ [has_user_request]
analyzing_requirements
  ↓ [analyzing_complete]
questioning (loop while confidence < 0.75)
  ↓ [confidence >= 0.75]
planning_machine
  ↓ [plan_complete]
generating_yaml
  ↓ [yaml_generated]
generating_functions
  ↓ [functions_generated]
validating_output
  ↓ [validation_passed]
delivering_machine
  ↓ [delivery_complete]
finished
```

### Extended State Context Schema

```php
[
    // Input & Requirements
    'user_request' => string,              // Initial natural language request
    'requirements' => array,               // Accumulated specification details
    'qa_history' => array,                 // Question-answer pairs

    // Confidence & Analysis
    'confidence_score' => float,           // 0.0-1.0, starts at 0.0
    'confidence_threshold' => float,       // 0.75 (configurable)
    'machine_complexity' => string,        // 'simple'|'moderate'|'complex'
    'required_features' => array,          // List of feature class names

    // Planning
    'state_flow' => array,                 // Planned states and transitions
    'context_variables' => array,          // ExtendedState variables needed
    'abilities_needed' => array,           // Abilities to expose

    // Generation
    'holon_yaml' => string,                // Generated YAML content
    'holon_functions' => string,           // Generated PHP functions
    'machine_name' => string,              // Kebab-case machine identifier

    // Documentation
    'holon_spec' => array,                 // Loaded Holon format specification
    'examples' => array,                   // Example machines for AI reference

    // Control
    'should_exit' => bool,                 // User exit flag
    'validation_errors' => array,          // Validation issues found
]
```

---

## Feature Stack

```yaml
machine:
  require: !php require '/var/www/html/machines/machine-agent/holon-functions.php'

  features:
    # Core infrastructure (order matters!)
    - class: Noem\State\Feature\ExtendedState\ExtendedState

    # AI capabilities
    - class: Noem\State\Feature\Template\TemplateFeature
    - class: Noem\State\Feature\Ai\AiFeature
    - class: Noem\State\Feature\Ai\AiConfigFeature
      config:
        credentials:
          anthropic:
            # Uses ANTHROPIC_API_KEY env var
          ollama:
            baseUrl: 'http://telvanni:7863/api'

        modelPool:
          - id: 'claude-sonnet'
            provider: 'anthropic'
            model: 'claude-sonnet-4'
            complexity: 3
            context: 200
            cost: 'high'
          - id: 'qwen-coder'
            provider: 'ollama'
            model: 'qwen2.5-coder:7b'
            complexity: 2
            context: 32
            cost: 'low'
          - id: 'llama-3'
            provider: 'ollama'
            model: 'llama3.2:3b'
            complexity: 1
            context: 8
            cost: 'free'

        preferences:
          defaultComplexity: 2
          defaultContext: 32
          preferProviders: ['ollama', 'anthropic']
          costBias: 'moderate'

        weave:
          defaultMaxIterations: 3
          defaultBackend: 'anthropic'
          defaultComplexity: 'high'

    # Async & messaging
    - class: Noem\State\Feature\Async\AsyncFeature
    - class: Noem\State\Feature\Message\MessageFeature

    # Abilities & agentic operations
    - class: Noem\State\Feature\Abilities\AbilitiesFeature
    - class: Noem\State\Feature\Agentic\AgenticFeature

  eventLoop:
    autoRun: true
    maxIterations: 0  # Unlimited - interactive session
```

**Rationale**:
- **Anthropic (Claude Sonnet)**: Complex reasoning, code generation (high quality)
- **Ollama (Qwen Coder)**: Moderate complexity, code assistance (local, fast)
- **Ollama (Llama 3)**: Simple tasks, classification (very fast, free)
- **ModelPool**: Automatic backend selection based on task complexity
- **AgenticFeature**: Enables `weave()` for autonomous tool orchestration

---

## State Definitions

### 1. `idle` - Initialization

**Purpose**: Entry point, display welcome, initialize context

**onEnter**:
```php
function onEnterIdle() {
    return function(object $t): void {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "   MACHINE AGENT - Autonomous State Machine Generator\n";
        echo "   Powered by Noem State Machine + AI\n";
        echo str_repeat('=', 70) . "\n\n";
        echo "Describe the state machine you want to create.\n";
        echo "I'll ask clarifying questions and generate a complete Holon.\n\n";
        echo "Type 'exit' to cancel.\n\n";

        // Initialize context
        $this->set('requirements', []);
        $this->set('qa_history', []);
        $this->set('confidence_score', 0.0);
        $this->set('confidence_threshold', 0.75);
        $this->set('should_exit', false);
        $this->set('validation_errors', []);

        // Load Holon specification
        $specPath = '/var/www/html/machines/machine-agent/holon-spec.yaml';
        $this->set('holon_spec', yaml_parse_file($specPath));

        // Load example machines
        $examples = [
            'conversational-cli' => yaml_parse_file('/var/www/html/machines/conversational-cli/holon.yml'),
            'task-executor' => yaml_parse_file('/var/www/html/machines/task-executor/holon.yml'),
        ];
        $this->set('examples', $examples);
    };
}
```

**Transitions**: Unconditional → `gathering_requirements`

---

### 2. `gathering_requirements` - Get User Input

**Purpose**: Prompt for and capture initial request

**onEnter**:
```php
function onEnterGatheringRequirements() {
    return function(object $t): void {
        echo "Describe your machine: ";
        flush();
    };
}
```

**Action** (async):
```php
function actionGatheringRequirements() {
    return function(object $t): Generator {
        $input = fgets(STDIN);

        if ($input !== false) {
            $userInput = trim($input);

            if (in_array(strtolower($userInput), ['exit', 'quit', 'cancel'])) {
                $this->set('should_exit', true);
                return;
            }

            if (!empty($userInput)) {
                $this->set('user_request', $userInput);

                // Add to requirements
                $requirements = $this->get('requirements');
                $requirements['initial_request'] = $userInput;
                $this->set('requirements', $requirements);

                return;
            }
        }

        yield;
    };
}
```

**Guards**:
- `guardHasUserRequest`: `$this->get('user_request') !== null`
- `guardShouldExit`: `$this->get('should_exit') === true`

**Transitions**:
- → `analyzing_requirements` [has_user_request]
- → `finished` [should_exit]

---

### 3. `analyzing_requirements` - Initial Analysis

**Purpose**: Use AI to analyze request, assess confidence, identify missing info

**Action** (async):
```php
function actionAnalyzingRequirements() {
    return function(object $t): Generator {
        echo "\n[Analyzing your request...]\n";

        $userRequest = $this->get('user_request');
        $requirements = $this->get('requirements');

        $analysisSchema = [
            'type' => 'object',
            'required' => ['confidence', 'complexity', 'missing_info', 'identified_requirements'],
            'properties' => [
                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                    'description' => 'Confidence that you can create the machine (0.0-1.0)'
                ],
                'complexity' => [
                    'type' => 'string',
                    'enum' => ['simple', 'moderate', 'complex'],
                    'description' => 'Estimated machine complexity'
                ],
                'missing_info' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'List of unclear or missing information needed'
                ],
                'identified_requirements' => [
                    'type' => 'object',
                    'description' => 'Extracted structured requirements'
                ]
            ]
        ];

        $prompt = <<<PROMPT
Analyze this state machine request:

User Request: {$userRequest}

Current Requirements: {json_encode($requirements, JSON_PRETTY_PRINT)}

Provide:
1. Confidence score (0.0-1.0) - how confident are you that you can create this machine?
2. Complexity assessment (simple/moderate/complex)
3. List of missing information needed to implement the machine
4. Structured requirements extracted from the request

Consider:
- What states are needed?
- What transitions and guards?
- What features are required (AI, Async, Abilities, etc.)?
- What context variables are needed?
- What external interactions (stdin, files, API calls)?
- Are there any ambiguities?
PROMPT;

        $analysis = yield from $this->capture($prompt, $analysisSchema, backend: 'anthropic', complexity: 'high');

        $this->set('confidence_score', $analysis['confidence']);
        $this->set('machine_complexity', $analysis['complexity']);
        $this->set('missing_info', $analysis['missing_info']);

        // Merge identified requirements
        $requirements = array_merge($requirements, $analysis['identified_requirements']);
        $this->set('requirements', $requirements);

        echo sprintf(
            "[Confidence: %.0f%% | Complexity: %s | Missing: %d items]\n",
            $analysis['confidence'] * 100,
            $analysis['complexity'],
            count($analysis['missing_info'])
        );

        $this->set('analysis_complete', true);

        yield;
    };
}
```

**Guards**:
- `guardAnalysisComplete`: `$this->get('analysis_complete') === true`

**Transitions**: → `questioning` [analysis_complete]

---

### 4. `questioning` - Interactive Clarification Loop

**Purpose**: Ask questions until confidence threshold reached

**onEnter**:
```php
function onEnterQuestioning() {
    return function(object $t): void {
        $confidence = $this->get('confidence_score');
        $threshold = $this->get('confidence_threshold');

        if ($confidence >= $threshold) {
            // Skip questioning
            $this->set('questioning_complete', true);
            return;
        }

        echo "\n[Need more information]\n";
        $this->set('questioning_complete', false);
    };
}
```

**Action** (async, uses weave):
```php
function actionQuestioning() {
    return function(object $t): Generator {
        $confidence = $this->get('confidence_score');
        $threshold = $this->get('confidence_threshold');

        if ($confidence >= $threshold) {
            $this->set('questioning_complete', true);
            return;
        }

        $userRequest = $this->get('user_request');
        $requirements = $this->get('requirements');
        $missingInfo = $this->get('missing_info');
        $qaHistory = $this->get('qa_history');

        // Use weave() to autonomously ask questions
        $intent = <<<INTENT
Based on the user's request for a state machine and the identified missing information,
ask ONE focused clarifying question that will help increase confidence in the implementation.

User Request: {$userRequest}
Current Requirements: {json_encode($requirements, JSON_PRETTY_PRINT)}
Missing Information: {json_encode($missingInfo, JSON_PRETTY_PRINT)}
Previous Q&A: {json_encode($qaHistory, JSON_PRETTY_PRINT)}

Return a single, specific question that addresses the most critical missing piece.
INTENT;

        // Generate question using AI
        $questionSchema = [
            'type' => 'object',
            'required' => ['question'],
            'properties' => [
                'question' => ['type' => 'string', 'description' => 'The clarifying question to ask']
            ]
        ];

        $result = yield from $this->capture($intent, $questionSchema, backend: 'anthropic');
        $question = $result['question'];

        echo "\n{$question}\n> ";
        flush();

        // Get user response
        $answer = null;
        while ($answer === null) {
            $input = fgets(STDIN);
            if ($input !== false) {
                $answer = trim($input);

                if (in_array(strtolower($answer), ['exit', 'quit', 'cancel'])) {
                    $this->set('should_exit', true);
                    return;
                }
            }
            yield;
        }

        // Record Q&A
        $qaHistory[] = ['question' => $question, 'answer' => $answer];
        $this->set('qa_history', $qaHistory);

        // Update requirements with answer
        $requirements['qa_' . count($qaHistory)] = [
            'question' => $question,
            'answer' => $answer
        ];
        $this->set('requirements', $requirements);

        // Re-analyze confidence with new information
        echo "\n[Reassessing with new information...]\n";

        $reassessmentSchema = [
            'type' => 'object',
            'required' => ['confidence', 'missing_info', 'updated_requirements'],
            'properties' => [
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'missing_info' => ['type' => 'array', 'items' => ['type' => 'string']],
                'updated_requirements' => ['type' => 'object']
            ]
        ];

        $reassessment = yield from $this->capture(
            "Reassess confidence based on new Q&A:\n" . json_encode(['requirements' => $requirements, 'qa_history' => $qaHistory], JSON_PRETTY_PRINT),
            $reassessmentSchema,
            backend: 'anthropic'
        );

        $this->set('confidence_score', $reassessment['confidence']);
        $this->set('missing_info', $reassessment['missing_info']);
        $this->set('requirements', array_merge($requirements, $reassessment['updated_requirements']));

        echo sprintf("[Confidence: %.0f%%]\n", $reassessment['confidence'] * 100);

        // Check if we're done questioning
        if ($reassessment['confidence'] >= $threshold) {
            $this->set('questioning_complete', true);
        }

        yield;
    };
}
```

**Guards**:
- `guardQuestioningComplete`: `$this->get('questioning_complete') === true`
- `guardShouldExit`: `$this->get('should_exit') === true`

**Transitions**:
- → `questioning` (self-loop) [!questioning_complete && !should_exit]
- → `planning_machine` [questioning_complete]
- → `finished` [should_exit]

---

### 5. `planning_machine` - Create Implementation Plan

**Purpose**: Generate detailed state flow, features, context schema

**Action** (async):
```php
function actionPlanningMachine() {
    return function(object $t): Generator {
        echo "\n[Planning machine architecture...]\n";

        $requirements = $this->get('requirements');
        $holon_spec = $this->get('holon_spec');
        $examples = $this->get('examples');

        $planSchema = [
            'type' => 'object',
            'required' => ['machine_name', 'states', 'features', 'context_variables'],
            'properties' => [
                'machine_name' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9-]*$'],
                'states' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'purpose' => ['type' => 'string'],
                            'transitions' => ['type' => 'array']
                        ]
                    ]
                ],
                'features' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'List of feature class names needed'
                ],
                'context_variables' => [
                    'type' => 'object',
                    'description' => 'ExtendedState context schema'
                ],
                'abilities_needed' => [
                    'type' => 'array',
                    'items' => ['type' => 'object'],
                    'description' => 'Abilities to register'
                ]
            ]
        ];

        $prompt = <<<PROMPT
Create a detailed implementation plan for this state machine:

Requirements: {json_encode($requirements, JSON_PRETTY_PRINT)}

Holon Format Specification:
{yaml_emit($holon_spec)}

Example Machines for Reference:
{json_encode($examples, JSON_PRETTY_PRINT)}

Provide a complete plan including:
1. Machine name (kebab-case)
2. State definitions (name, purpose, transitions)
3. Required features (full class names)
4. Context variable schema
5. Abilities to expose (if any)

Follow the patterns shown in the examples. Keep it simple and focused.
PROMPT;

        $plan = yield from $this->capture($prompt, $planSchema, backend: 'anthropic', complexity: 'high');

        $this->set('machine_name', $plan['machine_name']);
        $this->set('state_flow', $plan['states']);
        $this->set('required_features', $plan['features']);
        $this->set('context_variables', $plan['context_variables']);
        $this->set('abilities_needed', $plan['abilities_needed'] ?? []);

        echo "[Plan created: {$plan['machine_name']} with " . count($plan['states']) . " states]\n";
        $this->set('plan_complete', true);

        yield;
    };
}
```

**Guards**: `guardPlanComplete`: `$this->get('plan_complete') === true`

**Transitions**: → `generating_yaml` [plan_complete]

---

### 6. `generating_yaml` - Generate Holon YAML

**Purpose**: Create complete holon.yml file

**Action** (async):
```php
function actionGeneratingYaml() {
    return function(object $t): Generator {
        echo "\n[Generating holon.yml...]\n";

        $machineName = $this->get('machine_name');
        $stateFlow = $this->get('state_flow');
        $requiredFeatures = $this->get('required_features');
        $requirements = $this->get('requirements');
        $examples = $this->get('examples');

        $prompt = <<<PROMPT
Generate a complete Holon YAML file for this machine.

Machine Name: {$machineName}
State Flow: {json_encode($stateFlow, JSON_PRETTY_PRINT)}
Required Features: {json_encode($requiredFeatures, JSON_PRETTY_PRINT)}
Requirements: {json_encode($requirements, JSON_PRETTY_PRINT)}

Examples for Reference:
{json_encode($examples, JSON_PRETTY_PRINT)}

Follow these rules:
1. Use !php return functionName() for all callbacks
2. Include machine.require to load holon-functions.php
3. Set appropriate eventLoop.maxIterations (0 for interactive, limited for batch)
4. Include all necessary features in correct order
5. Define clear state flow with onEnter, action (async), transitions, guards
6. Mark async actions with async.enabled: true
7. Set initial and final states

Return ONLY valid YAML content, no markdown or explanations.
PROMPT;

        $yaml = yield from $this->complete($prompt, backend: 'anthropic', complexity: 'high');

        // Clean any markdown wrapping
        $yaml = preg_replace('/^```ya?ml\n/', '', $yaml);
        $yaml = preg_replace('/\n```$/', '', $yaml);

        $this->set('holon_yaml', $yaml);

        echo "[YAML generated: " . strlen($yaml) . " bytes]\n";
        $this->set('yaml_generated', true);

        yield;
    };
}
```

**Guards**: `guardYamlGenerated`: `$this->get('yaml_generated') === true`

**Transitions**: → `generating_functions` [yaml_generated]

---

### 7. `generating_functions` - Generate PHP Functions

**Purpose**: Create holon-functions.php with all callbacks

**Action** (async):
```php
function actionGeneratingFunctions() {
    return function(object $t): Generator {
        echo "\n[Generating holon-functions.php...]\n";

        $machineName = $this->get('machine_name');
        $stateFlow = $this->get('state_flow');
        $contextVariables = $this->get('context_variables');
        $requirements = $this->get('requirements');
        $abilitiesNeeded = $this->get('abilities_needed');

        $prompt = <<<PROMPT
Generate complete PHP functions file for this Holon machine.

Machine: {$machineName}
States: {json_encode($stateFlow, JSON_PRETTY_PRINT)}
Context Schema: {json_encode($contextVariables, JSON_PRETTY_PRINT)}
Requirements: {json_encode($requirements, JSON_PRETTY_PRINT)}
Abilities: {json_encode($abilitiesNeeded, JSON_PRETTY_PRINT)}

Create module-level functions following this pattern:
```php
function onEnterStateName() {
    return function(object \$t): void {
        // Use \$this->get() and \$this->set() for context
    };
}

function actionStateName() {
    return function(object \$t): Generator {
        // Async actions must yield
        // Use \$this->complete(), \$this->capture(), etc.
        yield;
    };
}

function guardConditionName() {
    return function(object \$t): bool {
        return \$this->get('variable') === value;
    };
}
```

Rules:
1. Include <?php declare(strict_types=1);
2. Module-level functions (NOT in a class)
3. Inner closures accept object \$t parameter
4. Return types: void for onEnter, Generator for async actions, bool for guards
5. Use ExtendedState API: \$this->get(), \$this->set()
6. Use AI helpers: \$this->complete(), \$this->capture(), \$this->template()
7. Use abilities: \$this->abilities('name', \$params)->then(...)
8. Add helpful comments

Return ONLY valid PHP code, no markdown or explanations.
PROMPT;

        $php = yield from $this->complete($prompt, backend: 'anthropic', complexity: 'high');

        // Clean any markdown wrapping
        $php = preg_replace('/^```php\n/', '', $php);
        $php = preg_replace('/\n```$/', '', $php);

        $this->set('holon_functions', $php);

        echo "[PHP functions generated: " . strlen($php) . " bytes]\n";
        $this->set('functions_generated', true);

        yield;
    };
}
```

**Guards**: `guardFunctionsGenerated`: `$this->get('functions_generated') === true`

**Transitions**: → `validating_output` [functions_generated]

---

### 8. `validating_output` - Validate Generated Code

**Purpose**: Syntax check, YAML validation, sanity checks

**Action** (async):
```php
function actionValidatingOutput() {
    return function(object $t): Generator {
        echo "\n[Validating generated machine...]\n";

        $yaml = $this->get('holon_yaml');
        $php = $this->get('holon_functions');
        $errors = [];

        // Validate YAML syntax
        try {
            yaml_parse($yaml);
            echo "✓ YAML syntax valid\n";
        } catch (\Exception $e) {
            $errors[] = "YAML syntax error: " . $e->getMessage();
            echo "✗ YAML syntax error\n";
        }

        // Validate PHP syntax
        $phpCheck = shell_exec('php -l 2>&1 <<EOF' . "\n" . $php . "\nEOF\n");
        if (strpos($phpCheck, 'No syntax errors') !== false) {
            echo "✓ PHP syntax valid\n";
        } else {
            $errors[] = "PHP syntax error: " . $phpCheck;
            echo "✗ PHP syntax error\n";
        }

        // Structural validation
        $structure = yaml_parse($yaml);
        if (!isset($structure['states']) || empty($structure['states'])) {
            $errors[] = "Missing or empty states definition";
        }
        if (!isset($structure['initial'])) {
            $errors[] = "Missing initial state";
        }
        if (!isset($structure['final'])) {
            $errors[] = "Missing final state";
        }

        $this->set('validation_errors', $errors);

        if (empty($errors)) {
            echo "✓ All validations passed\n";
            $this->set('validation_passed', true);
        } else {
            echo "✗ Validation failed with " . count($errors) . " error(s)\n";
            foreach ($errors as $error) {
                echo "  - {$error}\n";
            }
            $this->set('validation_passed', false);
        }

        yield;
    };
}
```

**Guards**:
- `guardValidationPassed`: `$this->get('validation_passed') === true`
- `guardValidationFailed`: `$this->get('validation_passed') === false`

**Transitions**:
- → `delivering_machine` [validation_passed]
- → `planning_machine` [validation_failed] (retry with error feedback)

---

### 9. `delivering_machine` - Save and Present Output

**Purpose**: Write files, display summary, show next steps

**Action**:
```php
function actionDeliveringMachine() {
    return function(object $t): Generator {
        echo "\n[Delivering machine...]\n\n";

        $machineName = $this->get('machine_name');
        $yaml = $this->get('holon_yaml');
        $php = $this->get('holon_functions');

        // Create machine directory
        $machineDir = "/var/www/html/machines/{$machineName}";
        if (!is_dir($machineDir)) {
            mkdir($machineDir, 0755, true);
        }

        // Write files
        file_put_contents("{$machineDir}/holon.yml", $yaml);
        file_put_contents("{$machineDir}/holon-functions.php", $php);

        echo str_repeat('=', 70) . "\n";
        echo "   MACHINE GENERATED SUCCESSFULLY\n";
        echo str_repeat('=', 70) . "\n\n";
        echo "Name: {$machineName}\n";
        echo "Location: {$machineDir}/\n\n";
        echo "Files created:\n";
        echo "  - holon.yml (" . strlen($yaml) . " bytes)\n";
        echo "  - holon-functions.php (" . strlen($php) . " bytes)\n\n";
        echo "To run your machine:\n";
        echo "  ddev exec php run.php machines/{$machineName}/holon.yml\n\n";
        echo str_repeat('=', 70) . "\n\n";

        $this->set('delivery_complete', true);

        yield;
    };
}
```

**Guards**: `guardDeliveryComplete`: `$this->get('delivery_complete') === true`

**Transitions**: → `finished` [delivery_complete]

---

### 10. `finished` - Graceful Exit

**onEnter**:
```php
function onEnterFinished() {
    return function(object $t): void {
        if ($this->get('should_exit')) {
            echo "\nOperation cancelled by user.\n\n";
        }
        // Clean exit
    };
}
```

---

## Abilities System

The machine exposes abilities for use by `weave()`:

### Ability: `ask-clarifying-question`

```php
// In holon-functions.php or via BuildStep
new RegisterAbility(
    name: 'ask-clarifying-question',
    description: 'Ask user a specific question and get their response',
    parameterSchema: [
        'type' => 'object',
        'required' => ['question'],
        'properties' => [
            'question' => ['type' => 'string', 'description' => 'The question to ask']
        ]
    ],
    responseSchema: [
        'type' => 'object',
        'properties' => [
            'answer' => ['type' => 'string']
        ]
    ],
    handler: function(array $params): array {
        echo "\n{$params['question']}\n> ";
        flush();
        $answer = trim(fgets(STDIN));
        return ['answer' => $answer];
    }
)
```

### Ability: `enumerate-abilities`

Built-in from AbilitiesFeature - lists all available abilities.

---

## Holon Specification File

Create `machines/machine-agent/holon-spec.yaml`:

```yaml
# Holon YAML Format Specification
# Complete reference for machine-agent to understand the format

format_version: "1.0"

machine_section:
  description: "Top-level configuration for the entire machine"

  require:
    syntax: "!php require '/absolute/path/to/functions.php'"
    purpose: "Load external PHP file with callback functions (module-level)"
    notes:
      - "Executes during bootstrap phase"
      - "Use absolute paths (no __DIR__ available in YAML context)"
      - "Defines module-level functions, not class methods"

  features:
    syntax: "Array of feature class definitions"
    example: |
      features:
        - class: Noem\State\Feature\ExtendedState\ExtendedState
        - class: Noem\State\Feature\Ai\AiConfigFeature
          config:
            anthropic:
              apiKey: "${ANTHROPIC_API_KEY}"
    order_matters: true
    notes:
      - "ExtendedState should be first"
      - "Features wrap in LIFO order"
      - "Some features have optional config parameter"

  eventLoop:
    autoRun:
      type: boolean
      description: "Automatically run event loop after building"
    maxIterations:
      type: integer
      description: "Max iterations (0 or -1 for unlimited)"
      examples:
        - 0     # Unlimited (interactive)
        - 100   # Limited (batch processing)
        - 10000 # Default

states_section:
  description: "Array of state definitions"

  state_definition:
    required_fields:
      - name

    optional_fields:
      - onEnter
      - onExit
      - action
      - transitions

    name:
      type: string
      description: "Unique state identifier"

    onEnter:
      syntax: "Array of callback definitions"
      example: |
        onEnter:
          - run: !php return onEnterStateName()
      notes:
        - "Executes once when entering state"
        - "Return type: void"

    action:
      syntax: "Array of callback definitions with optional async config"
      example: |
        action:
          - run: !php return actionStateName()
            async:
              enabled: true
              singleton: true  # Optional
      notes:
        - "Executes repeatedly while in state"
        - "Return type: Generator for async, void for sync"
        - "Must yield at least once if async"

    transitions:
      syntax: "Array of transition definitions"
      example: |
        transitions:
          - target: next_state
            guard: !php return guardCondition()
      notes:
        - "Evaluated in order"
        - "First matching guard triggers transition"
        - "Guard return type: bool"

callback_signatures:
  onEnter_onExit:
    pattern: |
      function callbackName() {
          return function(object $t): void {
              // Logic here
          };
      }

  action_sync:
    pattern: |
      function actionName() {
          return function(object $t): void {
              // Synchronous logic
          };
      }

  action_async:
    pattern: |
      function actionName() {
          return function(object $t): Generator {
              // Async logic
              yield;  // Must yield!
          };
      }

  guard:
    pattern: |
      function guardName() {
          return function(object $t): bool {
              return $this->get('condition') === true;
          };
      }

context_api:
  description: "ExtendedState context methods (available as $this->)"

  methods:
    get:
      signature: "get(string $key): mixed"
      example: "$value = $this->get('user_input');"

    set:
      signature: "set(string $key, mixed $value): void"
      example: "$this->set('counter', 42);"

    complete:
      signature: "complete(string $prompt, array $options = []): Generator"
      example: "$result = yield from $this->complete('Prompt', backend: 'anthropic');"
      returns: "Generator yielding string chunks"

    capture:
      signature: "capture(string $prompt, array $schema, array $options = []): Generator"
      example: "$data = yield from $this->capture($prompt, $schema);"
      returns: "Generator yielding array matching schema"

    template:
      signature: "template(string $template): Generator"
      example: "$output = yield from $this->template('{{#complete}}...');"

    abilities:
      signature: "abilities(string $name, mixed $params = null): Message"
      example: "$msg = $this->abilities('calc', ['x' => 5])->then($callback);"
      returns: "Message object with then() method"

    weave:
      signature: "weave(string $intent, array $options = []): Generator"
      example: "$result = yield from $this->weave('Find user and send email');"
      returns: "Generator yielding result array"

features_reference:
  ExtendedState:
    class: "Noem\\State\\Feature\\ExtendedState\\ExtendedState"
    provides: "$this->get(), $this->set()"
    required_for: "Context access in callbacks"

  AiFeature:
    class: "Noem\\State\\Feature\\Ai\\AiFeature"
    provides: "$this->complete(), $this->capture()"
    requires: "TemplateFeature"

  AsyncFeature:
    class: "Noem\\State\\Feature\\Async\\AsyncFeature"
    provides: "Async action support, coroutine scheduler"
    requires: "ExtendedState"

  MessageFeature:
    class: "Noem\\State\\Feature\\Message\\MessageFeature"
    provides: "Message correlation, request-response pattern"

  AbilitiesFeature:
    class: "Noem\\State\\Feature\\Abilities\\AbilitiesFeature"
    provides: "$this->abilities(), enumerate-abilities"
    requires: "MessageFeature"

  AgenticFeature:
    class: "Noem\\State\\Feature\\Agentic\\AgenticFeature"
    provides: "$this->weave()"
    requires: "AbilitiesFeature, AiFeature"
```

---

## Runtime Configuration

```yaml
eventLoop:
  autoRun: true
  maxIterations: 0  # Unlimited for interactive session
```

**Rationale**: Interactive machine like conversational-cli and task-executor. Users may ask for multiple machine generations in one session.

---

## Output Validation Strategy

1. **YAML Syntax**: `yaml_parse()` - throws on invalid YAML
2. **PHP Syntax**: `php -l` via shell_exec
3. **Structural**: Check required keys (states, initial, final)
4. **Semantic** (future): Actually load the machine and test it

**Retry Strategy**: If validation fails, return to `planning_machine` with error feedback for AI to correct.

---

## Example Usage

```bash
$ ddev exec php run.php machines/machine-agent/holon.yml

======================================================================
   MACHINE AGENT - Autonomous State Machine Generator
   Powered by Noem State Machine + AI
======================================================================

Describe the state machine you want to create.
I'll ask clarifying questions and generate a complete Holon.

Type 'exit' to cancel.

Describe your machine: A file processor that validates JSON files in a directory

[Analyzing your request...]
[Confidence: 60% | Complexity: moderate | Missing: 3 items]

[Need more information]

Should the machine process files continuously or as a one-shot batch?
> One-shot batch - validate all files once and report results

[Reassessing with new information...]
[Confidence: 75%]

[Planning machine architecture...]
[Plan created: json-file-validator with 6 states]

[Generating holon.yml...]
[YAML generated: 2847 bytes]

[Generating holon-functions.php...]
[PHP functions generated: 5432 bytes]

[Validating generated machine...]
✓ YAML syntax valid
✓ PHP syntax valid
✓ All validations passed

[Delivering machine...]

======================================================================
   MACHINE GENERATED SUCCESSFULLY
======================================================================

Name: json-file-validator
Location: /var/www/html/machines/json-file-validator/

Files created:
  - holon.yml (2847 bytes)
  - holon-functions.php (5432 bytes)

To run your machine:
  ddev exec php run.php machines/json-file-validator/holon.yml

======================================================================
```

---

## Dependencies Summary

**Required Features**:
- ExtendedState - context access
- TemplateFeature - template rendering
- AiFeature - AI calls
- AiConfigFeature - backend configuration
- AsyncFeature - async actions
- MessageFeature - message correlation
- AbilitiesFeature - tool exposure
- AgenticFeature - weave() orchestration

**External Dependencies**:
- Anthropic API (via ANTHROPIC_API_KEY)
- Ollama (running at http://telvanni:7863/api)
- PHP YAML extension
- Symfony YAML parser

---

## Future Enhancements (Phase 2)

1. **Recursive/Layered Creation**: For complex machines, generate outer shell and recursively fill in details
2. **Machine Testing**: Auto-generate test cases and run them
3. **Interactive Refinement**: Allow user to request changes to generated machine
4. **Template Library**: Pre-built templates for common patterns
5. **Version Control**: Git integration for generated machines
6. **Machine Composition**: Combine multiple machines via OrthogonalRegions

---

## Implementation Checklist

- [ ] Create `machines/machine-agent/` directory
- [ ] Create `holon-spec.yaml` with complete format documentation
- [ ] Write `holon.yml` with all state definitions
- [ ] Write `holon-functions.php` with all callback implementations
- [ ] Test with simple machine request (e.g., "Hello World printer")
- [ ] Test with moderate complexity (e.g., "File processor")
- [ ] Test validation error handling
- [ ] Test questioning loop with varying confidence
- [ ] Document usage in `machines/machine-agent/README.md`

---

**Status**: Ready for spec-planner agent handover
