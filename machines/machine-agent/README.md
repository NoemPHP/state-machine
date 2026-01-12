# Machine Agent - Autonomous State Machine Generator

An AI-powered Holon machine that generates other Holon machines autonomously through natural language conversation.

## Overview

The Machine Agent is a self-contained state machine that:
1. **Accepts** natural language description of desired machine behavior
2. **Clarifies** requirements through interactive AI-powered questioning
3. **Analyzes** complexity and assesses confidence level
4. **Generates** complete Holon YAML + PHP function files
5. **Validates** generated code (syntax + structure)
6. **Delivers** ready-to-run machine

## Features

- **Interactive Questioning**: AI asks clarifying questions until confidence threshold reached (75%)
- **Multi-Backend AI**: Anthropic Claude for complex tasks, Ollama for local inference
- **Capability-Based Selection**: ModelPool automatically selects appropriate AI backend
- **Validation**: Syntax checking for both YAML and PHP
- **Smart Output**: Generates `holon.yml` with inline functions (simple) or separate `holon-functions.php` (complex)
- **Retry Logic**: Re-plans if validation fails

## Prerequisites

### Required

- **PHP 8.4+** with YAML extension

### AI Backend (choose one)

- **Anthropic Claude API** (recommended) - Set `ANTHROPIC_API_KEY` environment variable
  - Best for complex machine generation with full JSON schema support
  - Handles multi-step reasoning and code generation accurately

- **Ollama** (local, simplified mode) - Configure in holon.yml
  - Uses heuristic analysis instead of AI-powered requirement gathering
  - Suitable for simple machines with clear, detailed descriptions
  - Models tested: `qwen2.5-coder:14b-instruct-q4_K_M`, `qwen2.5:3b-instruct-q5_K_M`
  - **Limitation**: Smaller models struggle with JSON schema compliance

## Quick Start

```bash
# Set API key
export ANTHROPIC_API_KEY="your-key-here"

# Run the machine
ddev exec php run.php machines/machine-agent/holon.yml
```

## Usage Examples

### Example 1: Simple Machine (Inline Functions)

```
Describe your machine: Create a counter that counts from 1 to 10

[Analyzing your request...]
[Confidence: 95% | Complexity: simple]

[Planning machine architecture...]
[Plan created: counter with 3 states]

[Generating holon.yml...]
[YAML generated: 847 bytes]

[Validating generated machine...]
✓ YAML syntax valid
✓ Using inline functions (no separate file to validate)
✓ All validations passed

[Delivering machine...]

======================================================================
   MACHINE GENERATED SUCCESSFULLY
======================================================================

Name: counter
Location: machines/counter/

Files created:
  - holon.yml (847 bytes)

Note: This machine uses inline PHP functions (no separate file needed)

To run your machine:
  ddev exec php run.php machines/counter/holon.yml

======================================================================
```

### Example 2: Complex Machine (Separate File)

```
Describe your machine: Create a password generator that asks for length and complexity

[Analyzing your request...]
[Confidence: 65% | Complexity: moderate | Missing: 2 items]

[Need more information]

Should the password include special characters by default, or let the user choose?
> Let the user choose the character sets

[Reassessing with new information...]
[Confidence: 80%]

[Planning machine architecture...]
[Plan created: password-generator with 5 states]

[Generating holon.yml...]
[YAML generated: 1847 bytes]

[Generating holon-functions.php...]
[PHP functions generated: 3214 bytes]

[Validating generated machine...]
✓ YAML syntax valid
✓ PHP syntax valid
✓ All validations passed

[Delivering machine...]

======================================================================
   MACHINE GENERATED SUCCESSFULLY
======================================================================

Name: password-generator
Location: machines/password-generator/

Files created:
  - holon.yml (1847 bytes)
  - holon-functions.php (3214 bytes)

To run your machine:
  ddev exec php run.php machines/password-generator/holon.yml

======================================================================
```

## State Flow

```
idle
  ↓ [initialize context]
gathering_requirements
  ↓ [has_user_request]
analyzing_requirements
  ↓ [analyzing_complete]
questioning (confidence-driven loop)
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

## How It Works

### 1. Analysis Phase

The machine uses AI (Anthropic Claude) to analyze your request:
- Assesses confidence level (0.0-1.0)
- Identifies complexity (simple/moderate/complex)
- Lists missing information
- Extracts structured requirements

### 2. Questioning Phase

If confidence < 0.75, enters interactive clarification loop:
- AI generates focused clarifying questions
- User provides answers
- Confidence reassessed after each Q&A
- Continues until threshold reached

### 3. Planning Phase

AI creates detailed implementation plan:
- Machine name (kebab-case)
- State definitions with transitions
- Required features
- Context variable schema
- Abilities to expose (if any)

### 4. Generation Phase

AI intelligently chooses between two approaches:

#### Inline Functions (Simple Machines ≤ 4 states)

**holon.yml only**: Functions defined directly in YAML
- Minimal file overhead
- Simple callbacks inline with state definitions
- Example: `!php return function(object $t) { $this->set('ready', true); };`
- Best for: Simple state management, counters, basic workflows

#### Separate File (Complex Machines > 4 states)

**holon.yml + holon-functions.php**:
- Clear separation of configuration and logic
- Better IDE support and debugging
- Module-level functions for complex operations
- Best for: AI operations, multi-step async, complex business logic

### 5. Validation Phase

Syntax and structural checks:
- YAML parsing (via `yaml_parse()`)
- PHP syntax check (via `php -l`) - only for separate file approach
- Required keys check (states, initial, final)
- Retry planning if validation fails

### 6. Delivery Phase

Writes files to `machines/{machine-name}/` directory:
- Always: `holon.yml`
- Conditional: `holon-functions.php` (only for complex machines)

## Configuration

### AI Backends

The machine uses a **ModelPool** with three backends:

| Backend | Provider | Model | Complexity | Context | Cost | Use Case |
|---------|----------|-------|------------|---------|------|----------|
| claude-sonnet | Anthropic | claude-sonnet-4 | 3 | 200K | High | Complex reasoning, code generation |
| qwen-coder | Ollama | qwen2.5-coder:7b | 2 | 32K | Low | Moderate tasks, local inference |
| llama-3 | Ollama | llama3.2:3b | 1 | 8K | Free | Simple classification |

**Selection Strategy**:
1. Prefer Ollama for cost efficiency
2. Fallback to Anthropic for quality
3. Automatic complexity-based routing

### Confidence Threshold

Default: **0.75** (75%)

Lower values = fewer questions, less accuracy
Higher values = more questions, higher accuracy

Configurable in `onEnterIdle()` function:
```php
$this->set('confidence_threshold', 0.75);
```

## Architecture

### Feature Stack

1. **ExtendedState** - Context access (`$this->get/set`)
2. **TemplateFeature** - Mustache templates
3. **AiFeature** - AI completions (`$this->complete/capture`)
4. **AiConfigFeature** - Backend configuration + ModelPool
5. **AsyncFeature** - Coroutine support
6. **MessageFeature** - Message correlation
7. **AbilitiesFeature** - Tool exposure
8. **AgenticFeature** - Autonomous orchestration (`$this->weave`)

### Context Variables

| Variable | Type | Purpose |
|----------|------|---------|
| `user_request` | string | Initial natural language request |
| `requirements` | array | Accumulated specification details |
| `qa_history` | array | Question-answer pairs |
| `confidence_score` | float | 0.0-1.0 confidence level |
| `confidence_threshold` | float | Threshold for questioning (0.75) |
| `machine_complexity` | string | 'simple'/'moderate'/'complex' |
| `state_flow` | array | Planned states and transitions |
| `context_variables` | array | ExtendedState schema for new machine |
| `holon_yaml` | string | Generated YAML content |
| `holon_functions` | string | Generated PHP content |
| `machine_name` | string | Kebab-case identifier |
| `validation_errors` | array | Validation issues found |
| `should_exit` | bool | User cancellation flag |

## Files

### Machine-Agent Files

| File | Purpose |
|------|---------|
| `holon.yml` | Machine definition with inline callbacks |
| `bootstrap.php` | Infrastructure setup (autoloader, path helpers, file I/O) |
| `holon-spec.yaml` | Holon format specification (loaded by machine for generation) |
| `README.md` | This file |

**Architecture Note**: This machine demonstrates the **inline callback pattern** where all business logic is defined directly in `holon.yml`. The `bootstrap.php` file provides infrastructure concerns (PSR-4 autoloading, path resolution, atomic file writes) keeping the state machine focused on business logic.

#### Bootstrap Helpers

The `bootstrap.php` file provides the following helper functions:

**Path Resolution**:
- `machine_path(string $path)` - Resolve machine-relative paths
- `machines_path(string $name, string $file = '')` - Access other machines

**YAML Operations**:
- `load_yaml(string $path)` - Load and parse YAML with error handling

**Machine Generation**:
- `ensure_machine_directory(string $name)` - Create machine directory
- `atomic_write(string $path, string $content)` - Safe file writes
- `is_valid_machine_name(string $name)` - Validate kebab-case names

**PSR-4 Autoloader**:
- Classes under `MachineAgent\` namespace auto-load from `src/` directory

**Helper Classes** (autoloaded via `MachineAgent\` namespace):
- `AiHelper` - Simplifies AI operations (capture, complete, analyze, plan)
- `TemplateHelper` - Simplifies template streaming (YAML/PHP generation)

These helpers offload boilerplate code from inline callbacks, keeping business logic focused and concise.

#### Helper Class Usage

**AiHelper** - Encapsulates common AI patterns:
```php
$ai = new \MachineAgent\AiHelper($this);

// Analyze requirements with built-in schema
$analysis = $ai->analyzeRequirements($userRequest, $qaHistory);

// Generate clarifying question
$question = $ai->generateQuestion($missingInfo);

// Create implementation plan
$plan = $ai->createPlan($requirements, $holonSpec, $examples);
```

**TemplateHelper** - Simplifies template streaming:
```php
$tpl = new \MachineAgent\TemplateHelper($this);

// Stream AI completion
$yaml = yield from $tpl->streamComplete($prompt, 'ollama');

// Generate YAML with inline or separate functions
$yaml = yield from $tpl->generateYaml($name, $states, $features, $reqs, $examples, $useInline);
```

These helpers demonstrate how to keep inline callbacks clean while leveraging infrastructure code through autoloading.

### Generated Machine Files

When machine-agent creates a new machine, it generates:

| File | When Created | Purpose |
|------|--------------|---------|
| `holon.yml` | Always | State flow and configuration (may include inline functions) |
| `holon-functions.php` | Complex machines only (>4 states) | Callback implementations |

**Decision Logic**:
- **≤ 4 states**: Inline functions in YAML only
- **> 4 states**: Separate `holon-functions.php` file

## Limitations (MVP)

- **Single-layer machines only** - No recursive/hierarchical generation (Phase 2)
- **Simple to moderate complexity** - Targets machines like task-executor, conversational-cli
- **No machine testing** - Validates syntax only, not runtime behavior
- **No iterative refinement** - Cannot request changes after generation

## Troubleshooting

### "Confidence stuck below threshold"

If the machine keeps asking questions:
- Provide more specific answers
- Describe states and transitions explicitly
- Mention features needed (AI, async, etc.)
- Type 'exit' to cancel and try with clearer initial request

### "Validation failed"

If generated code fails validation:
- Machine automatically retries planning with error feedback
- Check validation error messages in output
- If persistent, the request may be too complex for current implementation

### "AI API errors"

- Verify `ANTHROPIC_API_KEY` is set correctly
- Check Ollama is running (if using local models)
- Reduce complexity by simplifying request

## Future Enhancements (Phase 2)

1. **Recursive Generation** - Generate complex machines layer-by-layer
2. **Machine Testing** - Auto-generate and run test cases
3. **Interactive Refinement** - Allow user to request changes
4. **Template Library** - Pre-built templates for common patterns
5. **Version Control** - Git integration for generated machines
6. **Machine Composition** - Combine machines via OrthogonalRegions

## Examples

### Simple Machine

**Request**: "Create a hello world printer"

**Generated**: 2 states (idle → print_hello → finished)

### Moderate Machine

**Request**: "Create a file processor that validates JSON files"

**Generated**: 5 states (initialize → load_files → validate_each → report_results → finished)

## Technical Details

### Module-Level Functions

All callbacks use **module-level functions** (not class methods) for ExtendedState compatibility:

```php
function callbackName() {
    return function(object $t): ReturnType {
        // $this-> bound to ExtendedState context at runtime
    };
}
```

### Async Actions

Actions that use AI, abilities, or I/O must return `Generator`:

```php
function actionStateName() {
    return function(object $t): \Generator {
        $result = yield from $this->capture($prompt, $schema);
        yield;  // Must yield at least once!
    };
}
```

### Guard Functions

Guards return boolean for transition conditions:

```php
function guardCondition() {
    return function(object $t): bool {
        return $this->get('ready') === true;
    };
}
```

## Support

For issues or questions:
- Review the generated machine's code
- Check `holon-spec.yaml` for format reference
- Examine `machines/conversational-cli/` and `machines/task-executor/` for examples

## License

Part of the Noem State Machine project.
