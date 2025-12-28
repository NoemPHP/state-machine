<?php

declare(strict_types=1);

// Machine Agent - Callback Functions
// Module-level functions for ExtendedState compatibility

// ============================================================================
// STATE: idle - Initialization
// ============================================================================

function onEnterIdle()
{
    return function (object $t): void {
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
        $specPath = __DIR__ . '/holon-spec.yaml';
        $this->set('holon_spec', yaml_parse_file($specPath));

        // Load example machines for AI reference
        $baseDir = dirname(__DIR__);
        $examples = [];

        if (file_exists("$baseDir/conversational-cli/holon.yml")) {
            $examples['conversational-cli'] = yaml_parse_file("$baseDir/conversational-cli/holon.yml");
        }

        if (file_exists("$baseDir/task-executor/holon.yml")) {
            $examples['task-executor'] = yaml_parse_file("$baseDir/task-executor/holon.yml");
        }

        $this->set('examples', $examples);
    };
}

// ============================================================================
// STATE: gathering_requirements - Get User Input
// ============================================================================

function onEnterGatheringRequirements()
{
    return function (object $t): void {
        echo "Describe your machine: ";
        flush();
    };
}

function actionGatheringRequirements()
{
    return function (object $t): \Generator {
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

function guardHasUserRequest()
{
    return function (object $t): bool {
        return $this->get('user_request') !== null;
    };
}

function guardShouldExit()
{
    return function (object $t): bool {
        return $this->get('should_exit') === true;
    };
}

// ============================================================================
// STATE: analyzing_requirements - Initial Analysis
// ============================================================================

function onEnterAnalyzingRequirements()
{
    return function (object $t): void {
        echo "\n[Analyzing your request...]\n";
    };
}

function actionAnalyzingRequirements()
{
    return function (object $t): \Generator {
        $userRequest = $this->get('user_request');
        $requirements = $this->get('requirements');
        $requirementsJson = json_encode($requirements, JSON_PRETTY_PRINT);

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

Current Requirements: {$requirementsJson}

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

        $analysis = $this->capture($prompt, $analysisSchema, 'ollama');

        if ($analysis === null || !is_array($analysis) || !isset($analysis['confidence'])) {
            echo "[ERROR: AI analysis failed - invalid response]\n";
            // Fallback to heuristic
            $wordCount = str_word_count($userRequest);
            $analysis = [
                'confidence' => ($wordCount > 10) ? 0.7 : 0.5,
                'complexity' => ($wordCount > 20) ? 'moderate' : 'simple',
                'missing_info' => ['More details about expected features'],
                'identified_requirements' => []
            ];
        }

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

function guardAnalysisComplete()
{
    return function (object $t): bool {
        return $this->get('analysis_complete') === true;
    };
}

// ============================================================================
// STATE: questioning - Interactive Clarification Loop
// ============================================================================

function onEnterQuestioning()
{
    return function (object $t): void {
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

function actionQuestioning()
{
    return function (object $t): \Generator {
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

        $requirementsJson = json_encode($requirements, JSON_PRETTY_PRINT);
        $missingInfoJson = json_encode($missingInfo, JSON_PRETTY_PRINT);
        $qaHistoryJson = json_encode($qaHistory, JSON_PRETTY_PRINT);

        // Generate clarifying question
        $questionSchema = [
            'type' => 'object',
            'required' => ['question'],
            'properties' => [
                'question' => ['type' => 'string']
            ]
        ];

        $intent = <<<INTENT
Ask ONE focused question to clarify this state machine request.

User Request: {$userRequest}
Missing Information: {$missingInfoJson}

Return a single, specific question.
INTENT;

        $result = $this->capture($intent, $questionSchema, 'ollama');

        if ($result === null || !is_array($result) || !isset($result['question'])) {
            // Fallback to simple question
            $question = !empty($missingInfo)
                ? $missingInfo[0] . "?"
                : "Could you provide more details about the expected behavior?";
        } else {
            $question = $result['question'];
        }

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
            'required' => ['confidence', 'missing_info'],
            'properties' => [
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'missing_info' => ['type' => 'array', 'items' => ['type' => 'string']]
            ]
        ];

        $reassessmentData = json_encode([
            'user_request' => $userRequest,
            'qa_history' => $qaHistory
        ], JSON_PRETTY_PRINT);

        $reassessment = $this->capture(
            "Reassess confidence:\n" . $reassessmentData,
            $reassessmentSchema,
            'ollama'
        );

        if ($reassessment === null || !is_array($reassessment) || !isset($reassessment['confidence'])) {
            // Fallback: heuristic increase
            $currentConfidence = $this->get('confidence_score');
            $reassessment = [
                'confidence' => min(0.9, $currentConfidence + 0.2),
                'missing_info' => []
            ];
        }

        $this->set('confidence_score', $reassessment['confidence']);
        $this->set('missing_info', $reassessment['missing_info']);
        $this->set('requirements', $requirements);

        echo sprintf("[Confidence: %.0f%%]\n", $reassessment['confidence'] * 100);

        // Check if we're done questioning
        if ($reassessment['confidence'] >= $threshold) {
            $this->set('questioning_complete', true);
        }

        yield;
    };
}

function guardQuestioningComplete()
{
    return function (object $t): bool {
        return $this->get('questioning_complete') === true;
    };
}

function guardQuestioningNotComplete()
{
    return function (object $t): bool {
        $complete = $this->get('questioning_complete');
        $shouldExit = $this->get('should_exit');
        return $complete !== true && $shouldExit !== true;
    };
}

// ============================================================================
// STATE: planning_machine - Create Implementation Plan
// ============================================================================

function onEnterPlanningMachine()
{
    return function (object $t): void {
        echo "\n[Planning machine architecture...]\n";
    };
}

function actionPlanningMachine()
{
    return function (object $t): \Generator {
        $requirements = $this->get('requirements');
        $holonSpec = $this->get('holon_spec');
        $examples = $this->get('examples');

        $requirementsJson = json_encode($requirements, JSON_PRETTY_PRINT);
        $holonSpecYaml = yaml_emit($holonSpec);
        $examplesJson = json_encode($examples, JSON_PRETTY_PRINT);

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

Requirements: {$requirementsJson}

Holon Format Specification:
{$holonSpecYaml}

Example Machines for Reference:
{$examplesJson}

Provide a complete plan including:
1. Machine name (kebab-case)
2. State definitions (name, purpose, transitions)
3. Required features (full class names)
4. Context variable schema
5. Abilities to expose (if any)

Follow the patterns shown in the examples. Keep it simple and focused.
PROMPT;

        $plan = $this->capture($prompt, $planSchema, 'ollama');

        if ($plan === null || !is_array($plan) || !isset($plan['machine_name'])) {
            echo "[ERROR: Planning failed - invalid response from Ollama]\n";
            echo "[Response: " . json_encode($plan) . "]\n";
            echo "[Cannot continue without a plan]\n";
            $this->set('should_exit', true);
            $this->set('plan_complete', false);
            yield;
            return;
        }

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

function guardPlanComplete()
{
    return function (object $t): bool {
        return $this->get('plan_complete') === true;
    };
}

// ============================================================================
// STATE: generating_yaml - Generate Holon YAML
// ============================================================================

function onEnterGeneratingYaml()
{
    return function (object $t): void {
        echo "\n[Generating holon.yml...]\n";
    };
}

function actionGeneratingYaml()
{
    return function (object $t): \Generator {
        $machineName = $this->get('machine_name');
        $stateFlow = $this->get('state_flow');
        $requiredFeatures = $this->get('required_features');
        $requirements = $this->get('requirements');
        $examples = $this->get('examples');

        $stateFlowJson = json_encode($stateFlow, JSON_PRETTY_PRINT);
        $requiredFeaturesJson = json_encode($requiredFeatures, JSON_PRETTY_PRINT);
        $requirementsJson = json_encode($requirements, JSON_PRETTY_PRINT);
        $examplesJson = json_encode($examples, JSON_PRETTY_PRINT);

        // Determine if we should use inline functions or separate file
        $stateCount = count($stateFlow);
        $useInline = $stateCount <= 4;  // Simple machines get inline functions

        $this->set('use_inline_functions', $useInline);

        if ($useInline) {
            $prompt = <<<PROMPT
Generate a complete Holon YAML file for this machine using INLINE PHP functions.

Machine Name: {$machineName}
State Flow: {$stateFlowJson}
Required Features: {$requiredFeaturesJson}
Requirements: {$requirementsJson}

Examples for Reference:
{$examplesJson}

CRITICAL REQUIREMENTS:
1. Output MUST be valid YAML text that can be parsed by Holon::fromYaml()
2. DO NOT include machine.require (no separate file needed)
3. Use INLINE !php return function(\$t) { ... }; for ALL callbacks
4. Keep inline functions simple (1-2 lines max)

Follow these rules:
1. Use inline functions: !php return function(\$t) { echo "Starting\\n"; \$this->set('started', true); };
2. For multiline, use: !php |
     return function(\$t) {
       // code here
     };
3. Set appropriate eventLoop.maxIterations (0 for interactive, limited for batch)
4. Include all necessary features in correct order (ExtendedState FIRST, AsyncFeature AFTER ExtendedState)
5. Define clear state flow with onEnter, action (async), transitions, guards
6. Mark async actions with async.enabled: true
7. Set initial and final states
8. Simple async: !php return function(\$t) { \$result = \$this->complete("prompt", "ollama"); yield; \$this->set('result', \$result); };

Return ONLY valid YAML content, no markdown code blocks, no explanations.
PROMPT;
        } else {
            $prompt = <<<PROMPT
Generate a complete Holon YAML file for this machine using SEPARATE PHP functions file.

Machine Name: {$machineName}
State Flow: {$stateFlowJson}
Required Features: {$requiredFeaturesJson}
Requirements: {$requirementsJson}

Examples for Reference:
{$examplesJson}

CRITICAL REQUIREMENTS:
1. Output MUST be valid YAML text that can be parsed by Holon::fromYaml()
2. MUST include machine.require to load holon-functions.php
3. Use function references: !php return functionName()

Follow these rules:
1. Use !php return functionName() for all callbacks
2. Include machine.require to load holon-functions.php (use container path: /var/www/html/machines/{$machineName}/holon-functions.php)
3. Set appropriate eventLoop.maxIterations (0 for interactive, limited for batch)
4. Include all necessary features in correct order (ExtendedState FIRST, AsyncFeature AFTER ExtendedState)
5. Define clear state flow with onEnter, action (async), transitions, guards
6. Mark async actions with async.enabled: true
7. Set initial and final states

Return ONLY valid YAML content, no markdown code blocks, no explanations.
PROMPT;
        }

        $this->set('promptText', $prompt);

        $template = $this->template(
            <<<'TEMPLATE'
{{#complete max=4000 backend="ollama"}}
{{promptText}}
{{/complete}}
TEMPLATE
        );

        assert($template instanceof Generator);

        $yaml = '';
        while ($template->valid()) {
            $chunk = $template->current();
            $yaml .= $chunk;
            $template->next();
            yield;
        }

        // Clean any markdown wrapping
        $yaml = preg_replace('/^```ya?ml\n/', '', $yaml);
        $yaml = preg_replace('/\n```$/', '', trim($yaml));

        $this->set('holon_yaml', $yaml);

        echo "[YAML generated: " . strlen($yaml) . " bytes]\n";
        $this->set('yaml_generated', true);

        yield;
    };
}

function guardYamlGeneratedNeedsFunctions()
{
    return function (object $t): bool {
        $yamlGenerated = $this->get('yaml_generated') === true;
        $useInline = $this->get('use_inline_functions') === true;
        return $yamlGenerated && !$useInline;  // Generated YAML and NOT using inline
    };
}

function guardYamlGeneratedInline()
{
    return function (object $t): bool {
        $yamlGenerated = $this->get('yaml_generated') === true;
        $useInline = $this->get('use_inline_functions') === true;
        return $yamlGenerated && $useInline;  // Generated YAML and using inline
    };
}

// ============================================================================
// STATE: generating_functions - Generate PHP Functions
// ============================================================================

function onEnterGeneratingFunctions()
{
    return function (object $t): void {
        echo "\n[Generating holon-functions.php...]\n";
    };
}

function actionGeneratingFunctions()
{
    return function (object $t): \Generator {
        $machineName = $this->get('machine_name');
        $stateFlow = $this->get('state_flow');
        $contextVariables = $this->get('context_variables');
        $requirements = $this->get('requirements');
        $abilitiesNeeded = $this->get('abilities_needed');

        $stateFlowJson = json_encode($stateFlow, JSON_PRETTY_PRINT);
        $contextVariablesJson = json_encode($contextVariables, JSON_PRETTY_PRINT);
        $requirementsJson = json_encode($requirements, JSON_PRETTY_PRINT);
        $abilitiesNeededJson = json_encode($abilitiesNeeded, JSON_PRETTY_PRINT);

        $prompt = <<<'PROMPT'
Generate complete PHP functions file for this Holon machine.

Machine: {machine_name}
States: {state_flow}
Context Schema: {context_variables}
Requirements: {requirements}
Abilities: {abilities_needed}

Create module-level functions following this pattern:
```php
function onEnterStateName() {
    return function(object $t): void {
        // Use $this->get() and $this->set() for context
    };
}

function actionStateName() {
    return function(object $t): Generator {
        // CRITICAL: capture() returns value directly, NOT a Generator
        $result = $this->capture($prompt, $schema, 'ollama');

        // MUST yield at least once for async scheduler
        yield;

        $this->set('result', $result);
    };
}

function guardConditionName() {
    return function(object $t): bool {
        return $this->get('variable') === value;
    };
}
```

CRITICAL RULES:
1. Include <?php declare(strict_types=1);
2. Module-level functions (NOT in a class)
3. Inner closures accept object $t parameter
4. Return types: void for onEnter, Generator for async actions, bool for guards
5. Use ExtendedState API: $this->get(), $this->set()
6. AI helpers: $this->capture($prompt, $schema, 'backend') returns value directly (NOT Generator)
7. AI helpers: $this->complete($prompt, 'backend') returns string directly (NOT Generator)
8. DO NOT use "yield from" with capture() or complete()
9. Async actions MUST yield at least once
10. Use abilities: $this->abilities('name', $params)->then(...)
11. Add helpful comments
12. Use \Generator type hint for async functions (fully qualified)

Return ONLY valid PHP code, no markdown code blocks, no explanations.
PROMPT;

        // Substitute variables into prompt
        $actualPrompt = str_replace(
            ['{machine_name}', '{state_flow}', '{context_variables}', '{requirements}', '{abilities_needed}'],
            [$machineName, $stateFlowJson, $contextVariablesJson, $requirementsJson, $abilitiesNeededJson],
            $prompt
        );

        $this->set('phpPromptText', $actualPrompt);

        $template = $this->template(
            <<<'TEMPLATE'
{{#complete max=8000 backend="ollama"}}
{{phpPromptText}}
{{/complete}}
TEMPLATE
        );

        assert($template instanceof Generator);

        $php = '';
        while ($template->valid()) {
            $chunk = $template->current();
            $php .= $chunk;
            $template->next();
            yield;
        }

        // Clean any markdown wrapping
        $php = preg_replace('/^```php\n/', '', $php);
        $php = preg_replace('/\n```$/', '', trim($php));

        $this->set('holon_functions', $php);

        echo "[PHP functions generated: " . strlen($php) . " bytes]\n";
        $this->set('functions_generated', true);

        yield;
    };
}

function guardFunctionsGenerated()
{
    return function (object $t): bool {
        return $this->get('functions_generated') === true;
    };
}

// ============================================================================
// STATE: validating_output - Validate Generated Code
// ============================================================================

function onEnterValidatingOutput()
{
    return function (object $t): void {
        echo "\n[Validating generated machine...]\n";
    };
}

function actionValidatingOutput()
{
    return function (object $t): \Generator {
        $yaml = $this->get('holon_yaml');
        $php = $this->get('holon_functions', '');
        $useInline = $this->get('use_inline_functions') === true;
        $errors = [];

        // Validate YAML syntax
        try {
            yaml_parse($yaml);
            echo "✓ YAML syntax valid\n";
        } catch (\Exception $e) {
            $errors[] = "YAML syntax error: " . $e->getMessage();
            echo "✗ YAML syntax error\n";
        }

        // Validate PHP syntax (only if using separate file)
        if (!$useInline && !empty($php)) {
            $tempFile = tempnam(sys_get_temp_dir(), 'machine_agent_php_');
            file_put_contents($tempFile, $php);
            $phpCheck = shell_exec("php -l " . escapeshellarg($tempFile) . " 2>&1");
            unlink($tempFile);

            if (strpos($phpCheck, 'No syntax errors') !== false) {
                echo "✓ PHP syntax valid\n";
            } else {
                $errors[] = "PHP syntax error: " . $phpCheck;
                echo "✗ PHP syntax error\n";
            }
        } elseif ($useInline) {
            echo "✓ Using inline functions (no separate file to validate)\n";
        }

        // Structural validation
        try {
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
        } catch (\Exception $e) {
            // Already caught above
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

function guardValidationPassed()
{
    return function (object $t): bool {
        return $this->get('validation_passed') === true;
    };
}

function guardValidationFailed()
{
    return function (object $t): bool {
        return $this->get('validation_passed') === false;
    };
}

// ============================================================================
// STATE: delivering_machine - Save and Present Output
// ============================================================================

function onEnterDeliveringMachine()
{
    return function (object $t): void {
        echo "\n[Delivering machine...]\n\n";
    };
}

function actionDeliveringMachine()
{
    return function (object $t): \Generator {
        $machineName = $this->get('machine_name');
        $yaml = $this->get('holon_yaml');
        $php = $this->get('holon_functions', '');
        $useInline = $this->get('use_inline_functions') === true;

        // Create machine directory
        $machineDir = __DIR__ . "/../{$machineName}";
        if (!is_dir($machineDir)) {
            mkdir($machineDir, 0755, true);
        }

        // Write files
        file_put_contents("{$machineDir}/holon.yml", $yaml);

        $filesCreated = ["holon.yml (" . strlen($yaml) . " bytes)"];

        if (!$useInline && !empty($php)) {
            file_put_contents("{$machineDir}/holon-functions.php", $php);
            $filesCreated[] = "holon-functions.php (" . strlen($php) . " bytes)";
        }

        echo str_repeat('=', 70) . "\n";
        echo "   MACHINE GENERATED SUCCESSFULLY\n";
        echo str_repeat('=', 70) . "\n\n";
        echo "Name: {$machineName}\n";
        echo "Location: {$machineDir}/\n\n";
        echo "Files created:\n";
        foreach ($filesCreated as $file) {
            echo "  - {$file}\n";
        }
        echo "\n";
        if ($useInline) {
            echo "Note: This machine uses inline PHP functions (no separate file needed)\n\n";
        }
        echo "To run your machine:\n";
        echo "  ddev exec php run.php machines/{$machineName}/holon.yml\n\n";
        echo str_repeat('=', 70) . "\n\n";

        $this->set('delivery_complete', true);

        yield;
    };
}

function guardDeliveryComplete()
{
    return function (object $t): bool {
        return $this->get('delivery_complete') === true;
    };
}

// ============================================================================
// STATE: finished - Graceful Exit
// ============================================================================

function onEnterFinished()
{
    return function (object $t): void {
        if ($this->get('should_exit')) {
            echo "\nOperation cancelled by user.\n\n";
        }
        // Clean exit
    };
}
