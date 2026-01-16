# PresentationFeature - Schema-Enforced State Exposure

## Purpose

PresentationFeature provides **declarative state exposure** for AI agents, monitoring dashboards, and debugging tools. It answers: **"What can this machine SHOW?"**

Where AbilitiesFeature exposes what a machine can DO, and InteractionFeature exposes what it can ASK, PresentationFeature exposes what it can SHOW—internal context values with rich metadata for rendering.

**Key Value**: External observers can discover and retrieve schema-validated state without knowing implementation details.

## Dependencies

**CRITICAL**: PresentationFeature requires three features loaded first:

```php
->enableFeatures(
    new ExtendedState(),       // For context storage
    new JsonSchemaFeature(),   // For schema validation
    new AbilitiesFeature(),    // For discovery abilities
    new PresentationFeature()  // Must come after all three
)
```

## Public API

### Context Helper: `$this->presentation()`

Register exposed state from within state callbacks:

```php
->onEnter('processing', function(object $trigger) {
    // Register a presentation (returns unregister callable)
    $unregister = $this->presentation(
        key: 'progress',           // Context key to expose
        label: 'Progress',         // Human-readable name
        intent: 'Task completion percentage'  // Semantic description
    );

    // Later: remove from discovery
    $unregister();
})
```

**Parameters**:
- `key` (string, required): Context field identifier matching JSON schema
- `label` (string, required): Human-readable display name for UI
- `intent` (string, required): Semantic meaning for context-aware rendering
- `metadata` (array, optional): Format hints (precision, unit, format)
- `predicate` (callable, optional): `fn(Region): bool` for conditional visibility

**Returns**: Callable that unregisters the presentation when called.

### RegionBuilder: `->presentation()`

Register presentations during build phase:

```php
$builder
    ->presentation('progress', 'Progress', 'Task completion percentage')
    ->presentation('status', 'Status', 'Current operation status', ['format' => 'badge'])
    ->build();
```

### Discovery Abilities

PresentationFeature registers two abilities for external introspection:

#### `enumerate-presentations`

Lists all registered presentations with schemas:

```php
$this->abilities('enumerate-presentations')
    ->then(function($response) {
        foreach ($response['presentations'] as $p) {
            echo "{$p['key']}: {$p['label']} - {$p['intent']}\n";
            // Each includes 'schema' for validation
        }
    });
```

**Response format**:
```php
[
    'presentations' => [
        [
            'key' => 'progress',
            'label' => 'Progress',
            'intent' => 'Task completion percentage',
            'metadata' => ['format' => 'percent'],
            'schema' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100]
        ]
    ]
]
```

#### `get-presented-state`

Retrieves actual context values:

```php
// Get all values
$this->abilities('get-presented-state')
    ->then(fn($response) => print_r($response['values']));

// Get specific keys
$this->abilities('get-presented-state', ['keys' => ['progress', 'status']])
    ->then(fn($response) => print_r($response['values']));
```

**Response format**:
```php
[
    'values' => [
        'progress' => [
            'value' => 75,
            'label' => 'Progress',
            'intent' => 'Task completion percentage',
            'metadata' => ['format' => 'percent']
        ]
    ]
]
```

## YAML Configuration

### Region-Level Presentations

```yaml
context:
  schema:
    - name: progress
      type: integer
      default: 0

presentations:
  - key: progress
    label: Progress
    intent: Task completion percentage
    metadata:
      format: percent
      min: 0
      max: 100

states:
  - name: processing
```

### State-Level Presentations

State-scoped presentations auto-generate predicates:

```yaml
states:
  - name: processing
    presentations:
      - key: currentItem
        label: Current Item
        intent: Item being processed
```

**Behavior**: `currentItem` presentation only visible when machine is in `processing` state.

## Architecture

### Component Overview

```
PresentationFeature
    ├── PresentationRegistry (Mesh-based storage)
    │   ├── register(RegionPresentation)
    │   ├── unregister(key)
    │   ├── get(key): ?RegionPresentation
    │   ├── all(): array
    │   └── getSchema(key): ?array
    │
    ├── RegionPresentation (immutable value object)
    │   ├── key: string
    │   ├── label: string
    │   ├── intent: string
    │   ├── metadata: ?array
    │   ├── predicate: ?callable
    │   └── jsonSerialize(): array (excludes predicate)
    │
    └── Discovery Abilities
        ├── enumerate-presentations
        └── get-presented-state
```

### Registration Flow

```
1. Registration via $this->presentation() or RegionBuilder
    ↓
2. PresentationRegistry.register() called
    ↓
3. Schema validation: key must exist in JsonSchema
    ↓
4. RegionPresentation stored by key
    ↓
5. Available for discovery via abilities
```

### Predicate Evaluation

```
enumerate-presentations called
    ↓
For each presentation:
    ├─ No predicate? → Include in result
    └─ Has predicate? → Evaluate predicate($region)
        ├─ Returns true → Include
        ├─ Returns false → Exclude
        └─ Throws exception → Exclude (graceful failure)
```

## Critical Idiosyncrasies

### 1. Schema Validation Required

**CRITICAL**: Every presentation key MUST have a corresponding JSON schema entry.

```yaml
# ❌ FAILS - no schema for 'progress'
presentations:
  - key: progress
    label: Progress
    intent: Percentage

# ✅ CORRECT - schema defined
context:
  schema:
    - name: progress
      type: integer
presentations:
  - key: progress
    label: Progress
    intent: Percentage
```

**Error**: `SchemaNotFoundException` thrown at registration time.

### 2. Predicate Receives Region, Not Context

```php
// ❌ WRONG - predicate doesn't receive context
$this->presentation('secret', 'Secret', 'Hidden value',
    predicate: fn($ctx) => $ctx->get('showSecrets')  // $ctx is Region!
);

// ✅ CORRECT - use Region to check state
$this->presentation('secret', 'Secret', 'Hidden value',
    predicate: fn(Region $region) => $region->currentState() === 'admin'
);
```

### 3. YAML Predicates Not Supported

```yaml
# ❌ NOT SUPPORTED - predicate in YAML
presentations:
  - key: progress
    label: Progress
    intent: Percentage
    predicate: !php return fn($r) => true;  # This won't work!

# ✅ Use state-level for auto-generated predicates
states:
  - name: processing
    presentations:
      - key: progress  # Auto-predicate: visible only in 'processing'
```

### 4. Unregister Callable is Instance-Specific

```php
$unregister1 = $this->presentation('key', 'Label', 'Intent');
$this->presentation('key', 'Label2', 'Different');  // Overwrites
$unregister1();  // Does NOT remove new registration

// Each registration returns unique unregister for THAT registration
```

### 5. Feature Loading Order

PresentationFeature extends schemas from JsonSchemaFeature and uses BoundAccess from ExtendedState:

```php
// ✅ CORRECT order
new ExtendedState(),
new JsonSchemaFeature(),
new AbilitiesFeature(),
new PresentationFeature()

// ❌ Will fail with missing dependencies
new PresentationFeature(),
new ExtendedState()
```

## Usage Patterns

### AI Agent State Introspection

```php
// AI agent discovers available state
$region->abilities('enumerate-presentations')->then(function($result) {
    $presentations = $result['presentations'];

    // AI can now intelligently query specific values
    $keys = array_column($presentations, 'key');

    $region->abilities('get-presented-state', ['keys' => $keys])
        ->then(fn($state) => analyzeState($state['values']));
});
```

### Conditional Visibility by State

```php
$builder
    ->presentation('inputData', 'Input', 'User input',
        predicate: fn(Region $r) => $r->currentState() === 'awaiting_input'
    )
    ->presentation('result', 'Result', 'Computation result',
        predicate: fn(Region $r) => $r->currentState() === 'complete'
    )
    ->build();
```

### Monitoring Dashboard

```php
// External system polls for updates
$loggingChain->link(function(LogParams $params, callable $next) {
    // Get current presented state
    $params->region->abilities('get-presented-state')
        ->then(fn($state) => sendToMonitoringService($state['values']));

    return $next($params);
});
```

### Rich Metadata for UI Rendering

```php
$this->presentation(
    key: 'temperature',
    label: 'Temperature',
    intent: 'Current sensor reading',
    metadata: [
        'unit' => 'celsius',
        'precision' => 1,
        'format' => 'gauge',
        'thresholds' => ['warning' => 80, 'critical' => 95]
    ]
);
```

## Relationship to Other Features

| Feature | Relationship | Notes |
|---------|--------------|-------|
| **ExtendedState** | Required | Provides context storage for values |
| **JsonSchemaFeature** | Required | Schema validation for exposed fields |
| **AbilitiesFeature** | Required | Discovery abilities infrastructure |
| **InteractionFeature** | Complement | Together form agentic trilogy |
| **MessageFeature** | Independent | Abilities use message correlation |

## Files Reference

| File | Purpose |
|------|---------|
| `PresentationFeature.php` | Main feature, ability registration, BoundAccess binding |
| `PresentationRegistry.php` | Mesh-based storage for presentations |
| `RegionPresentation.php` | Immutable value object |
| `SchemaNotFoundException.php` | Exception for missing schemas |

## Summary Checklist

When using PresentationFeature:

- [ ] Load ExtendedState, JsonSchemaFeature, AbilitiesFeature FIRST
- [ ] Define JSON schema for every presentation key
- [ ] Use `$this->presentation()` in callbacks for dynamic registration
- [ ] Use RegionBuilder `->presentation()` for build-time registration
- [ ] Predicates receive Region, not context (use `$region->currentState()`)
- [ ] YAML predicates not supported—use state-level for auto-predicates
- [ ] Store unregister callable if cleanup needed
- [ ] Discovery abilities return AbilityMessage—use `.then()` pattern

---

**Spec**: `specs/features/presentation.yaml`
**Status**: Stable, production-ready
