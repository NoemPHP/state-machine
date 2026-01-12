# Prefill Mechanism - Implementation Summary

## Problem Solved

Previously, when CLI agent summoned machine-agent, both would prompt the user for the same information, creating a poor UX with double prompting. The machine also hung when using `autoRun: false` because we couldn't provide data before starting.

## Solution: Interaction Prefill

Implemented a prefill mechanism that allows the InteractionAdapter to pre-fill responses for specific interaction IDs. When the machine requests that interaction, the adapter immediately provides the prefilled value without prompting the user.

## Changes Made

### 1. InteractionRequest Base Class
**File**: `src/Feature/Interaction/InteractionRequest.php`

Added `interactionId` parameter to enable prefill identification:

```php
public function __construct(
    public readonly string $question,
    public readonly ?string $context = null,
    public readonly ?int $timeoutMs = null,
    public readonly ?string $interactionId = null,  // NEW
    ?string $correlationId = null
)
```

### 2. Concrete Request Classes
**Files**:
- `src/Feature/Interaction/PromptRequest.php`
- `src/Feature/Interaction/ConfirmRequest.php`
- `src/Feature/Interaction/SelectRequest.php`
- `src/Feature/Interaction/ChoiceRequest.php`

Updated all constructors to accept and pass through `interactionId`:

```php
public function __construct(
    string $question,
    // ... other parameters ...
    ?string $interactionId = null,  // NEW
    ?string $correlationId = null
) {
    parent::__construct($question, $context, $timeoutMs, $interactionId, $correlationId);
}
```

### 3. InteractionFeature
**File**: `src/Feature/Interaction/InteractionFeature.php`

Updated `createRequestFromRegistry()` to pass interaction ID when creating requests:

```php
'prompt' => new PromptRequest(
    question: $question,
    // ... other parameters ...
    interactionId: $id  // NEW - passes the interaction ID
),
```

### 4. InteractionAdapter
**File**: `machines/cli-agent/src/InteractionAdapter.php`

Added prefill storage and logic:

```php
private array $prefilledResponses = [];

public function prefill(string $interactionId, mixed $value): void
{
    $this->prefilledResponses[$interactionId] = $value;
}

private function handleInteraction(InteractionRequest $request): object
{
    // Check if this interaction has a prefilled response
    if ($request->interactionId !== null &&
        isset($this->prefilledResponses[$request->interactionId])) {

        $prefilledValue = $this->prefilledResponses[$request->interactionId];

        // Create appropriate response type based on request type
        return match (true) {
            $request instanceof PromptRequest => $request->createResponse(
                PromptResponse::class,
                ['input' => $prefilledValue, 'cancelled' => false]
            ),
            // ... other types ...
        };
    }

    // No prefilled response - prompt the user
    // ... existing prompting logic ...
}
```

### 5. CLI Agent Configuration
**File**: `machines/cli-agent/holon.yml`

Updated `invoking_generator` state to use prefill:

```yaml
action:
  - run: !php |
      return function(object $t): \Generator {
          // Summon generator
          $generator = $this->summon(machines_path('machine-agent', 'holon.yml'));
          yield;

          // Attach interaction adapter
          $adapter = new \CliAgent\InteractionAdapter($generator, verbose: false);
          $adapter->attach();
          yield;

          // Prefill the initial description interaction
          $adapter->prefill('request-machine-description', $this->get('user_description'));
          yield;

          // Run generator (will use prefilled data)
          $runtime = new \Noem\State\StandardRuntime($generator);
          $runtime->run();
          yield;
      };
```

### 6. Machine Agent Cleanup
**File**: `machines/machine-agent/holon.yml`

- Removed trigger-based data passing logic from `idle` state
- Simplified `gathering_requirements` to just call `$this->interact()`
- Kept `autoRun: false` for manager control

## How It Works

1. **CLI Agent** collects user input once via STDIN
2. **CLI Agent** summons machine-agent and attaches InteractionAdapter
3. **CLI Agent** calls `$adapter->prefill('request-machine-description', $userInput)`
4. **CLI Agent** starts machine-agent via `$runtime->run()`
5. **Machine Agent** requests description via `$this->interact('request-machine-description')`
6. **InteractionAdapter** intercepts the request, finds prefilled value, delivers response immediately
7. **Machine Agent** receives response and continues processing (no user prompting!)

## Test Results

From `test_prefill_debug.php`:

```
=== Prefill Mechanism Debug Test ===

[1] Loading machine-agent...
    ✓ Machine-agent loaded

[2] Creating InteractionAdapter...
[InteractionAdapter] Attached to machine
    ✓ Adapter attached

[3] Prefilling 'request-machine-description' interaction...
    ✓ Prefilled with: 'A simple todo list manager'

[4] Running machine-agent...
    (Should automatically use prefilled description without prompting)

[InteractionAdapter] Received Noem\State\Feature\Interaction\PromptRequest interaction
[InteractionAdapter] Using prefilled response for 'request-machine-description'

[Planning machine architecture...]
```

## Benefits

- ✅ **No Double Prompting**: User is only asked once
- ✅ **Fluent UX**: Sub-agent doesn't know about CLI, all interaction is internal
- ✅ **Clean Separation**: Manager owns presentation, sub-agent owns domain logic
- ✅ **Flexible**: Can prefill any interaction by ID
- ✅ **Extensible**: Easy to add more prefilled interactions
- ✅ **Type-Safe**: Works with all interaction types (Prompt, Confirm, Select, Choice)

## Usage Pattern

For any manager-subagent interaction pattern:

```php
// 1. Summon child machine
$child = $this->summon('path/to/child.yml');

// 2. Create and attach adapter
$adapter = new InteractionAdapter($child);
$adapter->attach();

// 3. Prefill known interactions
$adapter->prefill('interaction-id', $knownValue);

// 4. Run child machine
$runtime = new StandardRuntime($child);
$runtime->run();  // Child receives prefilled values automatically
```

## Related Documentation

- `AGENT_COMMUNICATION_STRATEGY.md` - Overall manager-subagent pattern
- `src/Feature/Interaction/` - Interaction feature implementation
- `machines/cli-agent/` - Example manager agent
- `machines/machine-agent/` - Example domain agent
