# Agent Communication Strategy - Fluent UX Pattern

## Problem

When a manager agent (CLI agent) summons a sub-agent (machine-agent), both are trying to interact with the end user, causing:
- Duplicate prompts
- Duplicate banners
- Confusing UX (who is asking?)
- Sub-agent coupled to specific UI (CLI)

## Solution Architecture

### Separation of Concerns

**Manager Agent (CLI Agent)**
- **Owns**: All end-user interaction (STDIN/STDOUT)
- **Displays**: Single banner, all prompts, all status
- **Collects**: User input once
- **Delegates**: Domain logic to sub-agents
- **Handles**: InteractionRequest events from sub-agents

**Sub-Agent (Machine Agent)**
- **Owns**: Pure domain logic only
- **NO**: Console output, banners, prompts
- **Communicates**: ONLY via internal APIs (interactions, events)
- **Accepts**: Initial data via trigger payload
- **Emits**: Interactions when more info needed

### Communication Flow

```
User Input
    ↓
CLI Agent (collects once)
    ↓
Summon Sub-Agent with initial payload
    ↓
Sub-Agent processes with provided data
    ↓
Sub-Agent emits InteractionRequest (if more info needed)
    ↓
CLI Agent InteractionAdapter handles request
    ↓
CLI Agent prompts user (single point)
    ↓
Response delivered back to sub-agent
    ↓
Sub-Agent continues processing
    ↓
Results returned to CLI Agent
```

## Implementation Changes

### 1. Machine-Agent: Remove ALL Console Output

**Files to modify**:
- `machines/machine-agent/holon.yml`

**Changes**:
```yaml
# BEFORE (coupled to CLI)
- name: idle
  onEnter:
    - run: !php |
        return function(object $t): void {
            echo str_repeat('=', 70) . "\n";
            echo "   MACHINE AGENT - Autonomous State Machine Generator\n";
            # ... more echo statements
        };

# AFTER (generic, no UI coupling)
- name: idle
  onEnter:
    - run: !php |
        return function(object $t): void {
            // Initialize from trigger payload if provided
            $initialDescription = $t->description ?? null;
            if ($initialDescription) {
                $this->set('user_request', $initialDescription);
            }

            // Set up context silently
            $this->set('requirements', []);
            $this->set('qa_history', []);
            $this->set('confidence_score', 0.0);
        };
```

### 2. Machine-Agent: Accept Initial Data via Trigger

**Pattern**: CLI agent provides initial description via trigger payload

```php
// In CLI agent
$generator->trigger((object)[
    'type' => 'start',
    'description' => $userInput,  // Collected by CLI agent
]);
```

```yaml
# In machine-agent
- name: gathering_requirements
  action:
    - run: !php |
        return function(object $t): \Generator {
            // Check if description already provided via trigger
            if (!empty($this->get('user_request', ''))) {
                // Skip interaction, already have data
                yield;
                return;
            }

            // Only ask if not provided
            try {
                $input = yield from $this->interact('request-machine-description');
                $this->set('user_request', $input);
            } catch (\Noem\State\Feature\Interaction\InteractionCancelledException $e) {
                $this->set('should_exit', true);
            }
            yield;
        };
```

### 3. Machine-Agent: Replace Status Messages with Events

**BEFORE** (direct output):
```php
echo "[Analyzing your request...]\n";
```

**AFTER** (event emission):
```php
$this->dispatch((object)[
    'type' => 'status',
    'message' => 'Analyzing your request...',
]);
```

**CLI Agent** can subscribe to status events:
```php
$generator->subscribe('status', function($event) {
    echo "[" . $event->message . "]\n";
});
```

### 4. CLI Agent: Single Entry Point for User Interaction

```yaml
# machines/cli-agent/holon.yml
- name: gathering_input
  onEnter:
    - run: !php |
        return function(object $t): void {
            // Single banner
            echo "==============================\n";
            echo "  Machine Generator\n";
            echo "==============================\n\n";
        };
  action:
    - run: !php |
        return function(object $t): \Generator {
            // Collect input ONCE
            echo "Describe your machine: ";
            $input = trim(fgets(STDIN));

            if (strtolower($input) === 'exit') {
                $this->set('should_exit', true);
                yield;
                return;
            }

            $this->set('user_description', $input);
            yield;
        };

- name: invoking_generator
  action:
    - run: !php |
        return function(object $t): \Generator {
            echo "\n[Processing request...]\n\n";

            // Summon sub-agent
            $generator = $this->summon(machines_path('machine-agent', 'holon.yml'));
            yield;

            // Attach adapter (handles any additional interactions)
            $adapter = new \CliAgent\InteractionAdapter($generator, verbose: false);
            $adapter->attach();
            yield;

            // Trigger with initial data
            $generator->trigger((object)[
                'type' => 'start',
                'description' => $this->get('user_description'),
            ]);
            yield;

            // Run to completion
            $runtime = new \Noem\State\StandardRuntime($generator);
            $runtime->run();
            yield;

            // Extract results...
        };
```

### 5. Optional: Status Event Subscription

**Machine-Agent** emits progress events:
```php
$this->dispatch((object)[
    'type' => 'progress',
    'stage' => 'analyzing',
    'confidence' => 0.75,
]);
```

**CLI Agent** subscribes and displays:
```php
$generator->subscribe('progress', function($event) {
    echo sprintf(
        "[%s: %d%%]\n",
        ucfirst($event->stage),
        (int)($event->confidence * 100)
    );
});
```

## Benefits

✅ **Single Source of Truth**: CLI agent controls all user-facing interaction
✅ **Reusable Sub-Agents**: Machine-agent can be used by web UI, API, or any manager
✅ **Clean Separation**: Domain logic (machine-agent) vs presentation (CLI agent)
✅ **Testable**: Sub-agent can be tested without mocking STDIN/STDOUT
✅ **Composable**: Multiple sub-agents can be orchestrated by one manager
✅ **No Duplication**: User only sees prompts from manager agent

## Testing Pattern

```php
// Test sub-agent without UI
$machine = summon('machine-agent');

// Provide initial data via trigger
$machine->trigger((object)['description' => 'test machine']);

// Mock interaction handler
$machine->notificationChain->subscribe(function($event) {
    if ($event instanceof InteractionRequest) {
        $event->deliverResponse(
            $event->createResponse(PromptResponse::class, [
                'input' => 'test answer',
                'cancelled' => false,
            ])
        );
    }
});

// Run and assert
$runtime = new StandardRuntime($machine);
$runtime->run();

assert($machine->get('delivery_complete') === true);
```

## Migration Checklist

- [ ] Remove all `echo` statements from machine-agent
- [ ] Remove banner display from machine-agent `onEnter('idle')`
- [ ] Update `gathering_requirements` to accept initial data from trigger
- [ ] Update `analyzing_requirements` to emit events instead of echo
- [ ] Update `questioning` to emit events instead of echo
- [ ] Update all states to remove status echoes
- [ ] CLI agent: collect input once in initial state
- [ ] CLI agent: pass input via trigger payload
- [ ] CLI agent: subscribe to status events (optional)
- [ ] Test: single prompt appears
- [ ] Test: sub-agent works without UI

## Future Enhancements

1. **Structured Events**: Define event schema for status/progress
2. **Event Registry**: Similar to InteractionRegistry but for status events
3. **Adapters for Different UIs**: Web adapter, API adapter, etc.
4. **Progress Tracking**: Standard progress event format
5. **Error Reporting**: Structured error events vs console output

---

**Last Updated**: 2026-01-09
**Status**: Implementation Strategy
