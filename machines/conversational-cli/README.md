# Conversational CLI Machine

A **fully working Holon example** demonstrating interactive AI chat via CLI. This is the first complete single-file Holon machine showcasing async operations, AI integration, streaming responses, and multi-turn conversations with unlimited iterations.

## Features

- ✅ **Complete Holon YAML**: Single-file machine configuration with external function library
- ✅ **Unlimited Iterations**: Uses `maxIterations: 0` for indefinite execution
- ✅ **Async Input Handling**: Blocking stdin reading for interactive terminal use
- ✅ **AI Integration**: Claude AI-powered responses via AiFeature + TemplateFeature
- ✅ **Streaming Responses**: Real-time AI response chunks
- ✅ **Conversation History**: Context-aware multi-turn dialogue
- ✅ **ExtendedState Binding**: Proper runtime `$this` binding for state context

## Quick Start

### Prerequisites

```bash
# Set your Claude API key
export ANTHROPIC_API_KEY="your-api-key-here"
```

### Running Interactively (Recommended)

```bash
# Start the conversational CLI
ddev exec php run.php machines/conversational-cli/holon.yml
```

**Important**: This machine is designed for **interactive terminal use**. Type your messages and press Enter. Type `exit`, `quit`, or `bye` to end.

### Why Piped Input Doesn't Work

```bash
# ❌ This WON'T work for multi-turn conversation
echo "hello" | ddev exec php run.php machines/conversational-cli/holon.yml

# ❌ This will only process the first line
ddev exec php run.php machines/conversational-cli/holon.yml <<EOF
hello
what's 2+2?
exit
EOF
```

**Reason**: When stdin is a pipe, it becomes a **one-shot file descriptor** that gets exhausted after reading. The machine reads the first line, processes it, transitions states, then returns to read again - but the pipe is already empty, so `fgets(STDIN)` returns `false` immediately instead of blocking for more input.

**For interactive use** (the intended use case), STDIN is the terminal, which blocks waiting for user input. This allows true multi-turn conversation.

## State Flow

```
idle
  ↓
waiting_for_input (blocking stdin read)
  ↓ [user types input]
processing (async AI generation with streaming)
  ↓
responding (cleanup + prepare for next turn)
  ↓
[loop back to waiting_for_input OR exit to finished]
```

### State Descriptions

| State | Purpose | Key Actions |
|-------|---------|-------------|
| **idle** | Entry point | Display welcome message, initialize conversation history |
| **waiting_for_input** | Read user input | Blocking `fgets(STDIN)`, check for exit commands, store input in context |
| **processing** | AI processing | Build prompt from history, stream AI response chunks, update history |
| **responding** | Prepare next turn | Clear temporary state (`user_input`, `ai_response`), check exit flag |
| **finished** | Exit | Display conversation stats, graceful shutdown |

## Architecture: Holon Pattern

This machine demonstrates the **Holon pattern with external function library** - a hybrid approach that combines single-file YAML configuration with proper PHP function definitions for ExtendedState compatibility.

### File Structure

```
machines/conversational-cli/
├── holon.yml              # Complete machine definition
├── holon-functions.php    # Module-level functions for runtime callbacks
└── README.md              # This file
```

### Why This Pattern?

**The Challenge**: ExtendedState requires runtime callbacks with unbound `$this` that gets bound to `Bound` context at runtime. Inline YAML `!php` blocks create closures bound to `PhpEvalHelper`, which breaks ExtendedState.

**The Solution**: Define callbacks as **module-level functions** (not class methods) in a separate PHP file, then reference them from YAML using `!php return functionName()`.

**Module-level functions**:
```php
// ✅ CORRECT - Module-level function returns unbound closure
function onEnterIdle() {
    return function (object $t): void {
        $this->set('started', true);  // ExtendedState will bind $this at runtime
    };
}
```

**Class methods** (don't use):
```php
// ❌ WRONG - Class method returns closure bound to class instance
class Machine {
    private function onEnterIdle() {
        return function (object $t): void {
            $this->set('started', true);  // $this is Machine, not Bound!
        };
    }
}
```

## Holon YAML Deep Dive

Let's examine `holon.yml` section by section:

### 1. Machine Configuration

```yaml
machine:
  require: !php require '/var/www/html/machines/conversational-cli/holon-functions.php'

  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\Template\TemplateFeature
    - class: Noem\State\Feature\Ai\AiFeature
    - class: Noem\State\Feature\Async\AsyncFeature
    - class: Noem\State\Feature\Message\MessageFeature

  eventLoop:
    autoRun: true
    maxIterations: 0  # Unlimited iterations
```

#### `machine.require`

**Purpose**: Load external PHP file containing callback functions.

**Execution**: Runs during **bootstrap phase** (before container or region building).

**Pattern**: Use `!php require` to execute PHP that defines functions in global scope.

**Why Absolute Path**: The `!php` helper evaluates in `PhpEvalHelper` context where `__DIR__` refers to the helper's directory, not the YAML file's directory. Use absolute paths or project root-relative paths.

#### `machine.features`

**Purpose**: Declare which features to load for this machine.

**Feature Order**: **CRITICAL** - Features wrap in LIFO order:
1. ExtendedState (must be first for context)
2. TemplateFeature (provides `$this->template()`)
3. AiFeature (requires TemplateFeature for `{{#complete}}`)
4. AsyncFeature (enables async actions)
5. MessageFeature (enables message passing)

**Loading**: Features are instantiated during region building and modify the `RegionBuilder` chain.

#### `machine.eventLoop`

**Purpose**: Configure automatic event loop execution.

**`autoRun: true`**: When enabled, `Holon::fromYaml()` automatically runs the event loop and returns the final result instead of returning the Region instance.

**`maxIterations: 0`**: **Unlimited iterations** - disables the iteration limit check. Perfect for long-running interactive processes. Without this, the machine would throw `MaxIterationsException` after 10,000 iterations (default limit).

**Implementation**: See `Holon.php:343-344`:
```php
while ($maxIterations <= 0 || $iteration < $maxIterations) {
    // Event loop continues indefinitely when maxIterations <= 0
}
```

### 2. States Configuration

Each state follows this structure:

```yaml
states:
  - name: state_name
    onEnter:
      - run: !php return callbackFunction()
    action:
      - run: !php return actionFunction()
        async:
          enabled: true
    transitions:
      - target: next_state
        guard: !php return guardFunction()
```

#### State: `idle`

```yaml
- name: idle
  onEnter:
    - run: !php return onEnterIdle()
  transitions:
    - target: waiting_for_input
```

**Purpose**: Entry point and initialization.

**`onEnterIdle()` Function**:
- Displays welcome banner
- Initializes ExtendedState context: `conversation_history`, `user_input`, `ai_response`, `should_exit`
- No action or guards - immediately transitions to `waiting_for_input`

**Key Pattern**: `!php return onEnterIdle()` - The `!php` helper evaluates the code, which calls the function, which returns a closure. That closure is what gets registered as the callback.

#### State: `waiting_for_input`

```yaml
- name: waiting_for_input
  onEnter:
    - run: !php return onEnterWaitingForInput()
  action:
    - run: !php return actionWaitingForInput()
      async:
        enabled: true
  transitions:
    - target: processing
      guard: !php return guardHasUserInput()
```

**Purpose**: Wait for and capture user input.

**`onEnterWaitingForInput()` Function**:
- Displays prompt: `\n> `
- Runs every time we enter this state (after each AI response)

**`actionWaitingForInput()` Function**:
- **Blocking I/O**: `fgets(STDIN)` blocks until user presses Enter
- **Async Action**: Marked as `async: enabled: true` to work with AsyncFeature
- **Generator**: Returns a Generator that yields periodically (async requirement)
- **Completes on Input**: When input is received, stores it in context and returns (action completes)
- **Exit Handling**: Checks for `exit`, `quit`, `bye` commands and sets `should_exit` flag

**Critical Detail**: This is **NOT** a singleton action. Each time we enter `waiting_for_input`, a fresh action starts. This ensures clean stdin state for each conversation turn.

**Why Blocking I/O Works**: Even though it's an async action, blocking on `fgets(STDIN)` is appropriate here because:
1. We're in a CLI context where blocking is expected behavior
2. There's no other work to do while waiting for input
3. Using global `STDIN` resource persists across action invocations

**Transition Guard**: `guardHasUserInput()` checks if `$this->get('user_input')` is not null. When input is captured, the guard becomes true and transitions to `processing`.

#### State: `processing`

```yaml
- name: processing
  onEnter:
    - run: !php return onEnterProcessing()
  action:
    - run: !php return actionProcessing()
      async:
        enabled: true
  transitions:
    - target: responding
      guard: !php return guardHasAiResponse()
```

**Purpose**: Generate AI response based on conversation history.

**`onEnterProcessing()` Function**:
- Displays: `\nAssistant: ` (with newline and flush)
- Prepares user for streaming response

**`actionProcessing()` Function**:
- **Builds Context**: Formats conversation history into text
- **Template Rendering**: Uses `$this->template()` with `{{#complete}}` helper
- **Streaming**: Iterates through template generator, yielding chunks
- **Display**: Echoes each chunk immediately with `flush()` for real-time output
- **Storage**: Accumulates full response, stores in context, updates conversation history

**Template Structure**:
```handlebars
{{#complete temperature=0.7 max=500}}
You are a helpful, friendly AI assistant engaged in a conversation via CLI.

Conversation History:
{{conversationContext}}

Instructions:
- Provide helpful, concise, and friendly responses
- Keep responses relatively brief (2-4 sentences typically)
- Be conversational and natural
- Don't use markdown formatting
- Don't start with "Assistant:" or similar prefixes
{{/complete}}
```

**How `{{#complete}}` Works**:
1. AiFeature registers the `complete` helper (see `AiFeature.php:46-127`)
2. Helper builds prompt from block content
3. Calls AI backend (Anthropic API) with streaming enabled
4. Yields text chunks as they arrive from the API
5. Each chunk flows through: AI → Generator → Template → Action → Echo

**Context Variables**: `{{conversationContext}}` is resolved from ExtendedState context set earlier in the action.

**Transition Guard**: `guardHasAiResponse()` becomes true when `$this->get('ai_response')` is set.

#### State: `responding`

```yaml
- name: responding
  onEnter:
    - run: !php return onEnterResponding()
  action:
    - run: !php return actionResponding()
      async:
        enabled: true
  transitions:
    - target: waiting_for_input
      guard: !php return guardResponseComplete()
    - target: finished
      guard: !php return guardConversationEnded()
```

**Purpose**: Clean up temporary state and decide next transition.

**`onEnterResponding()` Function**:
- Clears `user_input` and `ai_response` from context
- Prepares clean slate for next conversation turn

**`actionResponding()` Function**:
- Brief pause: `usleep(100000)` (100ms)
- Single yield for async compliance
- Allows async system to process any pending tasks

**Dual Transitions**:
1. **Loop Back**: `guardResponseComplete()` returns true if `should_exit` is false → returns to `waiting_for_input` for another turn
2. **Exit**: `guardConversationEnded()` returns true if `should_exit` is true → transitions to `finished`

**Guard Priority**: Guards are evaluated in order. If user typed "exit", `should_exit` is true, so `guardResponseComplete()` returns false and `guardConversationEnded()` returns true.

#### State: `finished`

```yaml
- name: finished
  onEnter:
    - run: !php return onEnterFinished()

initial: idle
final: finished
```

**Purpose**: Graceful shutdown and statistics display.

**`onEnterFinished()` Function**:
- Retrieves conversation history
- Counts total messages
- Displays summary:
  ```
  ------------------------------------------------------------
  Conversation ended.
  Total messages exchanged: N
  Thank you for chatting!
  ------------------------------------------------------------
  ```

**Initial/Final Markers**:
- `initial: idle` - Machine starts in this state
- `final: finished` - Machine completes when reaching this state

**Event Loop Termination**: When the region reaches a final state, the event loop stops (see `Holon.php:365-366`).

### 3. Module-Level Functions (holon-functions.php)

All callback functions follow this pattern:

```php
function callbackName() {
    return function (object $t): ReturnType {
        // Runtime logic with $this-> access
    };
}
```

**Key Points**:

1. **Outer Function**: Module-level (not in a class)
2. **Inner Closure**: Accepts `object $t` (trigger parameter)
3. **Return Type**: `void` for synchronous, `Generator` for async
4. **$this Access**: Uses `$this->get()` and `$this->set()` for ExtendedState

**Example - Simple Callback**:
```php
function onEnterWaitingForInput()
{
    return function (object $t): void {
        echo "\n> ";
    };
}
```

**Example - Async Generator**:
```php
function actionWaitingForInput()
{
    return function (object $t): Generator {
        $input = fgets(STDIN);
        if ($input !== false) {
            $userInput = trim($input);
            if (!empty($userInput)) {
                $this->set('user_input', $userInput);
                return;
            }
        }
        yield;
    };
}
```

**Example - Guard**:
```php
function guardHasUserInput()
{
    return function (object $t): bool {
        return $this->get('user_input') !== null;
    };
}
```

### 4. ExtendedState Context Variables

The machine maintains these context variables throughout execution:

| Variable | Type | Purpose |
|----------|------|---------|
| `conversation_history` | `array` | List of `['role' => 'user\|assistant', 'content' => '...']` entries |
| `user_input` | `string\|null` | Current user message (cleared after processing) |
| `ai_response` | `string\|null` | Current AI response (cleared after processing) |
| `should_exit` | `bool` | Flag set when user types exit command |
| `conversationContext` | `string` | Formatted history string for AI prompt |

**Flow**:
1. `idle`: Initialize all to empty/null/false
2. `waiting_for_input`: Set `user_input`, append to `conversation_history`
3. `processing`: Set `conversationContext`, set `ai_response`, append to `conversation_history`
4. `responding`: Clear `user_input` and `ai_response`
5. Repeat from step 2

## Technical Deep Dive

### The Container vs Context Problem (Solved!)

This machine demonstrates the solution to a critical Holon/ExtendedState integration challenge.

**The Problem**:
- Container = Build-time dependency injection (services defined before Region exists)
- Context = Runtime state (ExtendedState's `$this->get/set` available during execution)
- Closures created in YAML `!php` blocks are bound to `PhpEvalHelper` evaluation context
- ExtendedState's `PrepareInvokable` middleware rebinds closures to `Bound` context
- But if closure scope class is wrong, `$this->set()` fails

**The Solution** (multiple fixes applied):

1. **ExtendedState.php Fix**: Changed `bindTo($object)` to `bindTo($object, $object)` to rebind BOTH object and scope class

2. **ProcessArray.php Fix**: Added runtime rebinding proxy for untyped closures:
   ```php
   return function(object $t) use ($callback) {
       if ($callback instanceof \Closure) {
           $boundCallback = $callback->bindTo($this, $this);
           return $boundCallback($t);
       }
       return $callback($t);
   };
   ```

3. **Module-Level Functions**: Define callbacks outside class scope so they have no initial scope class binding

**Result**: Holon YAML can now use ExtendedState seamlessly!

### Unlimited Iterations Implementation

**Specification**: `specs/core/runtime.yaml` defines `maxIterations: 0` or `-1` as unlimited.

**Implementation** (3 event loop locations):

**Runtime.php** (base class):
```php
if ($this->config->maxIterations > 0 && $this->iteration >= $this->config->maxIterations) {
    throw new MaxIterationsException(...);
}
```

**Holon.php** (Holon event loop):
```php
while ($maxIterations <= 0 || $iteration < $maxIterations) {
    // Loop continues indefinitely when maxIterations <= 0
}

if ($maxIterations > 0 && $iteration >= $maxIterations && !$region->isFinal()) {
    throw new RuntimeException(...);
}
```

**SelfContainedLoader.php** (legacy loader):
```php
while (!$region->isFinal() && ($maxIterations <= 0 || $iteration < $maxIterations)) {
    // Same pattern
}
```

**Tests**: 11 new tests covering unlimited execution (see `tests/PHPUnit/Unit/Core/Runtime/RuntimeConfigUnlimitedIterationsTest.php` and `RuntimeUnlimitedExecutionTest.php`).

### Async Architecture

**AsyncFeature Integration**:
- All actions are marked `async: enabled: true`
- Action functions return `Generator` (yield at least once)
- `CoroutineScheduler` manages async task execution
- Blocking operations (like `fgets(STDIN)`) are wrapped in generators

**Why Generators**:
```php
function actionWaitingForInput() {
    return function (object $t): Generator {
        // Even blocking operations must yield
        $input = fgets(STDIN);  // Blocks here
        // ... process input ...
        yield;  // Required for async compliance
    };
}
```

**Scheduler Behavior**:
- Ticks each coroutine until complete
- Non-singleton actions complete and are removed from scheduler
- Returns control to event loop between ticks

### AI Streaming Flow

```
User Input
    ↓
actionProcessing() builds prompt
    ↓
$this->template('{{#complete}}...')
    ↓
TemplateFeature parses template
    ↓
AiFeature 'complete' helper invoked
    ↓
Anthropic API streaming request
    ↓
Chunks arrive: "Hello" "!" " How" " are" " you" "?"
    ↓
Each chunk:
  - Yielded by AI generator
  - Flows through template generator
  - Yielded by action generator
  - Echoed to terminal (with flush)
    ↓
Full response accumulated in buffer
    ↓
Stored in context when complete
```

## Example Session

```
$ ddev exec php run.php machines/conversational-cli/holon.yml

============================================================
   CONVERSATIONAL CLI MACHINE
   Powered by Noem State Machine + Claude AI
============================================================

Type your messages and press Enter to chat.
Type 'exit' or 'quit' to end the conversation.

> Hello! Tell me about state machines.