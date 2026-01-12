# PresentationFeature Proposal

## Executive Summary

PresentationFeature completes the **agentic framework trilogy** by providing schema-enforced declarative state exposure. Where **AbilitiesFeature** answers "What can this machine DO?" and **InteractionFeature** answers "What can this machine ASK?", PresentationFeature answers **"What can this machine SHOW?"**

This feature enables state machines to expose their internal context to external observers—AI agents, monitoring dashboards, debugging tools—in a structured, discoverable, and schema-validated manner.

### Key Design Decisions

**Unregistration Support**: Runtime-registered presentations return an unregister callable for cleanup:
```php
$unregister = $this->presentation('key', 'label', 'intent');
// Later: $unregister(); // Idempotent removal
```

**Predicates are Programmatic-Only**: YAML does not support explicit predicate definitions to keep configuration simple. State-level YAML presentations auto-generate predicates checking `currentState`. Custom predicate logic requires programmatic registration via `RegionBuilder.presentation()` or `BoundAccess.presentation()`.

**Specification Coverage**: 99 acceptance criteria across 13 feature groups (includes new unregistration-api feature).

## Vision and Motivation

### The Third Pillar Problem

Modern agentic systems require three fundamental capabilities:

1. **Operations (DO)** - AbilitiesFeature provides schema-validated operations
2. **Questions (ASK)** - InteractionFeature provides structured request-response patterns
3. **Observability (SHOW)** - **Missing until now**

Without structured observability:
- AI agents cannot understand machine state for decision-making
- Monitoring systems scrape arbitrary context fields without contracts
- Debugging tools lack semantic understanding of exposed data
- Documentation is disconnected from actual state exposure

### Use Cases

#### AI Agent Decision Making
```php
// Agent discovers what state is available
$agent->abilities('enumerate-presentations')->then(function($response) {
    // Sees: userCount (integer), lastSync (timestamp), errorRate (percentage)
    // Schema tells agent types, intent tells agent meaning
});

// Agent retrieves current values for decision
$agent->abilities('get-presented-state')->then(function($response) {
    if ($response['errorRate'] > 0.05) {
        // Agent decides to trigger error recovery
    }
});
```

#### Monitoring Dashboard
```yaml
# Machine declares what to monitor
presentations:
  - key: requestsPerSecond
    label: Request Rate
    intent: HTTP requests processed per second for capacity monitoring
    metadata:
      format: decimal
      precision: 2
      unit: req/s
```

Dashboard discovers presentations via `enumerate-presentations`, renders charts with proper formatting, and polls `get-presented-state` for updates.

#### Debugging Tool
```php
// Dev tool shows all exposed state with semantic context
$debugger->abilities('enumerate-presentations')->then(function($response) {
    foreach ($response['presentations'] as $p) {
        echo "{$p['label']}: {$p['intent']}\n";
        echo "Schema: " . json_encode($p['schema']) . "\n";
    }
});
```

### Why Schema Enforcement Matters

**Without schemas**, presentation becomes a free-for-all:
- No serialization guarantees (closures, resources exposed)
- No type contracts (integers as strings, nulls unexpectedly)
- No validation (malformed data breaks consumers)

**With JsonSchemaFeature dependency**, PresentationFeature guarantees:
- All exposed fields have JSON Schema definitions
- Values conform to declared types
- External agents can validate data before rendering
- Serialization is safe and predictable

## Design Principles

### 1. Consistency with Existing Patterns

PresentationFeature follows established patterns from AbilitiesFeature and InteractionFeature:

| Pattern | Implementation |
|---------|----------------|
| **Value Objects** | `RegionPresentation` (like `AbilityDefinition`, `InteractionDefinition`) |
| **Mesh Storage** | `PresentationRegistry` (like `AbilityRegistry`, `InteractionRegistry`) |
| **Predicates** | Conditional exposure (like abilities) |
| **Discovery Abilities** | `enumerate-presentations`, `get-presented-state` |
| **Message Correlation** | Uses `.then()` callbacks (following abilities pattern) |
| **YAML Support** | Region and state-level declarations (like interactions) |

### 2. Hard Dependency on JsonSchemaFeature

This is a **non-negotiable** design choice. PresentationFeature will:
- Check for JsonSchemaFeature presence during initialization
- Throw clear error if JsonSchemaFeature not loaded
- Validate schema existence at registration time (not runtime)
- Prevent exposure of unvalidated fields

**Rationale**: Without schemas, we cannot guarantee serializability or type safety, which defeats the purpose of structured observability.

### 3. Predicate-Based Conditional Visibility

Following AbilitiesFeature patterns, presentations support optional predicates:

```php
// Only show admin metrics when user is admin
$builder->presentation(
    key: 'adminMetrics',
    label: 'Admin Metrics',
    intent: 'Sensitive system metrics for administrators',
    predicate: fn(Region $region) => $region->getCurrentState() === 'admin_mode'
);
```

Predicates are:
- **Evaluated at enumeration time** (not registration time)
- **Not serialized** (callables excluded from JSON)
- **Exception-safe** (errors treated as false)

### 4. Separation of Metadata and Intent

Inspired by user feedback on format hints, PresentationFeature separates:

**Intent** - Semantic "why" in human language:
```php
intent: 'Current number of active user sessions for capacity planning'
```

**Metadata** - Structured rendering hints:
```php
metadata: [
    'format' => 'integer',
    'precision' => 0,
    'unit' => 'sessions'
]
```

This allows:
- LLMs to parse intent naturally
- UIs to use structured metadata for formatting
- Both approaches to coexist without conflict

### 5. Read-Only for v1

PresentationFeature v1 provides **read-only observability**:
- No write operations (`set-presented-state`)
- No reactive streams (WebSocket push)
- Poll-only access pattern

**Rationale**: Write operations require careful consideration of:
- Authorization (who can modify state)
- Validation (what values are allowed)
- Conflict resolution (concurrent modifications)
- Rollback/undo mechanisms

These are significant design challenges better addressed in v2 after observing v1 usage patterns.

## Architecture Overview

### Component Structure

```
PresentationFeature
├── RegionPresentation (Value Object)
│   ├── key: string (context field identifier)
│   ├── label: string (human-readable name)
│   ├── intent: string (semantic meaning)
│   ├── metadata: ?array (rendering hints)
│   └── predicate: ?callable (visibility condition)
│
├── PresentationRegistry (Mesh Storage)
│   ├── register(RegionPresentation) - validates schema, stores
│   ├── get(string $key) - retrieves by key
│   └── all() - returns all presentations
│
├── Discovery Abilities
│   ├── enumerate-presentations - list visible with schemas
│   └── get-presented-state - retrieve actual values
│
└── Registration APIs
    ├── RegionBuilder.presentation() - build-time
    └── BoundAccess.presentation() - runtime
```

### Data Flow

#### Registration Flow
```
1. User calls $builder->presentation('userCount', 'Active Users', '...')
   ↓
2. PresentationFeature validates 'userCount' exists in JsonSchema
   ↓
3. If valid: Creates RegionPresentation, stores in PresentationRegistry
   ↓
4. If invalid: Throws SchemaNotFoundException
   ↓
5. Presentation available for discovery immediately
```

#### Discovery Flow
```
1. Agent calls $this->abilities('enumerate-presentations')
   ↓
2. Ability retrieves all presentations from registry
   ↓
3. Evaluates predicates for each presentation
   ↓
4. Filters out presentations with false predicates
   ↓
5. For each visible presentation:
   - Serializes presentation metadata
   - Retrieves JSON Schema from JsonSchemaFeature
   - Bundles together
   ↓
6. Returns array via .then() callback
```

#### Value Retrieval Flow
```
1. Agent calls $this->abilities('get-presented-state', ['keys' => ['userCount']])
   ↓
2. Ability retrieves requested presentations from registry
   ↓
3. Evaluates predicates (skips hidden presentations)
   ↓
4. For each visible presentation:
   - Calls ExtendedState $this->get(key)
   - Bundles value with presentation metadata
   ↓
5. Returns key-value map via .then() callback
```

### Integration with JsonSchemaFeature

JsonSchemaFeature provides two capabilities PresentationFeature uses:

1. **Schema Storage** - Context fields have JSON Schema definitions
2. **Validation** - Schema enforcement already exists (abilities use it)

PresentationFeature adds:
- **Schema Lookup** - `register()` validates key against schemas
- **Schema Inclusion** - `enumerate-presentations` bundles schemas with presentations

```php
// Registration validates schema exists
$registry->register(new RegionPresentation(
    key: 'userCount',  // Must exist in context.schema
    // ...
));

// Enumeration includes schema
[
    'presentations' => [
        [
            'key' => 'userCount',
            'label' => 'Active Users',
            'schema' => ['type' => 'integer', 'minimum' => 0],  // From JsonSchemaFeature
        ]
    ]
]
```

### Integration with ExtendedState

PresentationFeature retrieves values through ExtendedState's standard API:

```php
// In get-presented-state ability handler
foreach ($visiblePresentations as $presentation) {
    $value = $this->get($presentation->key);
    $result[$presentation->key] = [
        'value' => $value,
        'label' => $presentation->label,
        'intent' => $presentation->intent,
        'metadata' => $presentation->metadata,
    ];
}
```

This ensures:
- Values come from standard context storage
- No special access privileges required
- Consistency with other ExtendedState access

## API Design

### RegionPresentation Value Object

```php
readonly class RegionPresentation implements JsonSerializable
{
    public function __construct(
        public string $key,              // Required: Context field identifier
        public string $label,            // Required: Human-readable display name
        public string $intent,           // Required: Semantic meaning
        public ?array $metadata = null,  // Optional: Rendering hints
        public ?callable $predicate = null,  // Optional: Visibility condition
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'intent' => $this->intent,
            'metadata' => $this->metadata,
            // Note: predicate excluded (not serializable)
        ];
    }
}
```

### PresentationRegistry

```php
class PresentationRegistry extends Mesh
{
    /**
     * Register presentation (validates schema exists)
     *
     * @throws SchemaNotFoundException if key not in JsonSchema
     */
    public function register(RegionPresentation $presentation): void;

    /**
     * Unregister presentation by key (idempotent)
     */
    public function unregister(string $key): void;

    /**
     * Retrieve presentation by key
     *
     * @return RegionPresentation|null
     */
    public function get(string $key): ?RegionPresentation;

    /**
     * Get all registered presentations (before predicate filtering)
     *
     * @return array<string, RegionPresentation>
     */
    public function all(): array;
}
```

### Registration APIs

#### RegionBuilder (Build-Time)

```php
$builder->presentation(
    key: 'userCount',
    label: 'Active Users',
    intent: 'Current number of logged-in users for capacity monitoring',
    metadata: ['format' => 'integer', 'unit' => 'users'],
    predicate: null,  // Optional
);
```

**Returns**: `RegionBuilder` (for chaining)

#### BoundAccess (Runtime)

```php
->onEnter('monitoring', function(object $trigger) {
    // Register presentation and get unregister callback
    $unregister = $this->presentation(
        key: 'requestRate',
        label: 'Request Rate',
        intent: 'HTTP requests per second',
        metadata: ['format' => 'decimal', 'precision' => 2]
    );

    // Store in context for cleanup on exit
    $this->set('unregisterRequestRate', $unregister);
})

->onExit('monitoring', function(object $trigger) {
    // Clean up presentation when leaving state
    $unregister = $this->get('unregisterRequestRate');
    if ($unregister) {
        $unregister();  // Idempotent - safe to call multiple times
    }
})
```

**Returns**: `callable` - Unregister function for cleanup

**Unregister Callable**:
- Removes presentation from PresentationRegistry
- Idempotent (safe to call multiple times)
- Unique per registration (multiple registrations of same key get different callables)
- Works even after key re-registration

### Discovery Abilities

#### enumerate-presentations

**Purpose**: List all visible presentations with schemas

**Parameters**: None

**Response Format**:
```php
[
    'presentations' => [
        [
            'key' => 'userCount',
            'label' => 'Active Users',
            'intent' => 'Current number of logged-in users...',
            'metadata' => ['format' => 'integer'],
            'schema' => ['type' => 'integer', 'minimum' => 0],
        ],
        // ... more presentations
    ]
]
```

**Behavior**:
- Retrieves all presentations from registry
- Evaluates predicates (excludes false)
- Includes null predicates (always visible)
- Fetches JSON Schema for each key
- Serializes to JSON-compatible arrays

**Usage**:
```php
$this->abilities('enumerate-presentations')
    ->then(function($response) {
        foreach ($response->parameters['presentations'] as $p) {
            echo "{$p['label']}: {$p['intent']}\n";
        }
    });
```

#### get-presented-state

**Purpose**: Retrieve actual context values for presentations

**Parameters**:
```php
[
    'keys' => ['userCount', 'requestRate'],  // Optional: subset, omit for all
]
```

**Response Format**:
```php
[
    'state' => [
        'userCount' => [
            'value' => 42,
            'label' => 'Active Users',
            'intent' => 'Current number...',
            'metadata' => ['format' => 'integer'],
        ],
        'requestRate' => [
            'value' => 123.45,
            'label' => 'Request Rate',
            'intent' => 'HTTP requests per second',
            'metadata' => ['format' => 'decimal', 'precision' => 2],
        ]
    ]
]
```

**Behavior**:
- Retrieves specified keys (or all if omitted)
- Evaluates predicates (skips hidden)
- Calls `$this->get(key)` for each visible presentation
- Bundles value with presentation metadata
- Ignores unknown keys without error

**Usage**:
```php
// Get all presented state
$this->abilities('get-presented-state')
    ->then(function($response) {
        $userCount = $response->parameters['state']['userCount']['value'];
    });

// Get specific keys
$this->abilities('get-presented-state', ['keys' => ['userCount']])
    ->then(function($response) {
        $userCount = $response->parameters['state']['userCount']['value'];
    });
```

### YAML Configuration

#### Region-Level Presentations

```yaml
context:
  schema:
    - name: userCount
      type: integer
      default: 0

presentations:
  - key: userCount
    label: Active Users
    intent: Current number of logged-in users for capacity monitoring
    metadata:
      format: integer
      unit: users
```

**Behavior**: Presentations always visible (no predicate)

#### State-Level Presentations

```yaml
context:
  schema:
    - name: progress
      type: number
      default: 0.0

states:
  - name: processing
    presentations:
      - key: progress
        label: Processing Progress
        intent: Percentage complete for current operation
        metadata:
          format: percentage
          precision: 1
```

**Behavior**: Auto-generates predicate checking `currentState === 'processing'`

**Important**: YAML does not support explicit predicate definitions. Predicates are programmatic-only:
- Region-level YAML presentations have no predicate (always visible)
- State-level YAML presentations auto-generate a state-checking predicate
- For custom predicate logic, use `RegionBuilder.presentation()` or `BoundAccess.presentation()` programmatically

#### Programmatic Predicates (Not in YAML)

```php
// Custom predicate combining state and context checks
->presentation(
    key: 'debugInfo',
    label: 'Debug Information',
    intent: 'Internal diagnostics for troubleshooting',
    metadata: null,
    predicate: fn($region) =>
        $region->getCurrentState() === 'error' &&
        $region->get('debugMode') === true
)
```

## Implementation Plan

### Phase 1: Core Infrastructure (Week 1)

1. **RegionPresentation Value Object**
   - Implement readonly properties
   - Implement JsonSerializable
   - Write unit tests

2. **PresentationRegistry**
   - Extend Mesh
   - Implement register(), get(), all()
   - Add schema validation in register()
   - Write unit tests

3. **PresentationFeature**
   - Implement Feature interface
   - Add dependency checks (JsonSchema, ExtendedState, Abilities)
   - Register PresentationRegistry in ChainMail
   - Write unit tests

### Phase 2: Registration APIs (Week 1-2)

4. **RegionBuilder Integration**
   - Add presentation() method
   - Delegate to PresentationRegistry
   - Return RegionBuilder for chaining
   - Write unit tests

5. **BoundAccess Integration**
   - Register presentation() in BoundAccess chain
   - Implement runtime registration with unregister callback
   - Add feature dependency check
   - Write unit tests

6. **Unregistration API**
   - Implement PresentationRegistry.unregister()
   - Ensure idempotent behavior
   - Return unique callable from BoundAccess.presentation()
   - Write unit tests for cleanup scenarios

### Phase 3: Discovery Abilities (Week 2)

7. **enumerate-presentations Ability**
   - Implement handler retrieving all presentations
   - Add predicate evaluation
   - Fetch schemas from JsonSchemaFeature
   - Serialize to JSON-compatible format
   - Write unit tests

8. **get-presented-state Ability**
   - Implement handler with optional keys parameter
   - Add predicate filtering
   - Retrieve values via ExtendedState
   - Bundle with metadata
   - Write unit tests

### Phase 4: YAML Support (Week 2-3)

9. **RegionLoader Schema Extension**
   - Hook into LoaderChains.Schema
   - Add presentations key at region level
   - Add presentations key at state level
   - Validate schema structure
   - Write unit tests

10. **EnhanceRegionBuilder Hook**
    - Process region-level presentations
    - Process state-level presentations
    - Generate predicates for state-scoped presentations
    - Write unit tests

### Phase 5: Integration Tests (Week 3)

11. **End-to-End Workflows**
    - YAML to enumeration workflow
    - Programmatic registration to retrieval workflow
    - State-scoped presentations workflow
    - Schema validation enforcement workflow
    - Unregistration cleanup workflow
    - Write integration tests

### Phase 6: Documentation (Week 3-4)

12. **Feature Documentation**
    - Create src/Feature/Presentation/CLAUDE.md
    - Document public APIs (including unregistration)
    - Document usage patterns
    - Document integration with other features
    - Clarify predicate limitations (programmatic-only)

13. **Example Machines**
    - Create example/monitoring-machine.yaml
    - Create example/agentic-machine.yaml
    - Demonstrate all features including unregistration

## Test Coverage Requirements

Following established patterns, each acceptance criterion requires:

1. **Unit Test** - Tests isolated behavior
2. **Test File Naming** - Matches criterion exactly
3. **One Test per Criterion** - No consolidation

Example coverage for `RegionPresentation`:
```
tests/PHPUnit/Unit/Feature/Presentation/Definition/
├── StoresKeyTest.php
├── StoresLabelTest.php
├── StoresIntentTest.php
├── StoresMetadataTest.php
├── StoresPredicateTest.php
├── ImmutabilityTest.php
├── ImplementsJsonSerializableTest.php
├── SerializationExcludesPredicateTest.php
└── SerializesAllPropertiesTest.php
```

Target coverage:
- 100% of public API methods
- All edge cases and error conditions
- Integration tests for workflows
- YAML loading scenarios

## Format Hints Strategy

### Dual Approach Philosophy

PresentationFeature supports two format hint strategies:

#### 1. Structured Metadata (Machine-Readable)

```php
metadata: [
    'format' => 'decimal',
    'precision' => 2,
    'unit' => 'req/s',
    'range' => ['min' => 0, 'max' => 1000]
]
```

**Use case**: UIs rendering charts, tables, graphs

**Benefits**:
- Parseable by machines
- Type-safe formatting decisions
- Consistent rendering across tools

#### 2. Natural Language Intent (Human-Readable)

```php
intent: 'HTTP requests per second (0-1000 range, 2 decimal places) for capacity monitoring'
```

**Use case**: LLM agents, documentation generation

**Benefits**:
- Natural language understanding
- Context-rich descriptions
- Flexible interpretation

### Recommendation

Use **both** for maximum compatibility:

```php
$builder->presentation(
    key: 'requestRate',
    label: 'Request Rate',
    intent: 'HTTP requests per second (0-1000 range, 2 decimals) for capacity monitoring',
    metadata: [
        'format' => 'decimal',
        'precision' => 2,
        'unit' => 'req/s',
        'range' => ['min' => 0, 'max' => 1000]
    ]
);
```

This allows:
- Dashboards parse metadata for formatting
- AI agents read intent for decision-making
- Documentation shows intent directly
- Both approaches coexist without conflict

## Migration Path

### For Existing Projects

PresentationFeature is **opt-in**. Existing projects can:

1. **Do Nothing** - No breaking changes
2. **Add Gradually** - Expose critical fields first
3. **Full Adoption** - Expose all observable state

### Example Migration

**Before** (unstructured monitoring):
```php
->onAction('processing', function(object $trigger) {
    $status = [
        'progress' => $this->get('progress'),
        'errors' => $this->get('errors'),
    ];
    // Custom emission logic
    emit_monitoring_event($status);
})
```

**After** (structured presentation):
```php
->enableFeatures(new PresentationFeature())
->presentation('progress', 'Progress', 'Processing completion percentage')
->presentation('errors', 'Error Count', 'Number of errors during processing')
->onAction('processing', function(object $trigger) {
    // No custom emission needed!
    // Monitoring tools discover via enumerate-presentations
    // Monitoring tools poll via get-presented-state
})
```

Benefits:
- Declarative instead of imperative
- Schema-validated automatically
- Discoverable by any tool
- Self-documenting

## Security Considerations

### What PresentationFeature Does NOT Provide

1. **Authorization** - No access control (v1 is read-only for all)
2. **Encryption** - No sensitive data protection
3. **Audit Logging** - No tracking of who accessed what
4. **Rate Limiting** - No query throttling

### Security Best Practices

1. **Don't expose secrets**
   ```php
   // ❌ BAD
   $builder->presentation('apiKey', 'API Key', '...');

   // ✅ GOOD
   $builder->presentation('apiKeyStatus', 'API Key Status', '...',
       predicate: fn() => $this->get('userRole') === 'admin'
   );
   ```

2. **Use predicates for sensitive data**
   ```php
   $builder->presentation(
       'internalMetrics',
       'Internal Metrics',
       '...',
       predicate: fn(Region $r) => $r->getCurrentState() === 'admin_mode'
   );
   ```

3. **Consider schema-level constraints**
   ```yaml
   context:
     schema:
       - name: userEmail
         type: string
         # Don't expose raw email

   presentations:
     - key: userEmailDomain  # Expose derived value instead
       label: User Domain
       intent: Email domain for analytics
   ```

## Performance Considerations

### Enumeration Performance

- **Predicate evaluation**: O(n) where n = number of presentations
- **Schema lookup**: O(n) lookups into JsonSchemaFeature
- **Optimization**: Cache schemas during enumeration

### Value Retrieval Performance

- **Context access**: O(n) where n = number of requested keys
- **Predicate evaluation**: O(n) for filtering
- **Optimization**: Batch context retrieval if possible

### Recommendations

1. **Keep presentation count reasonable** - < 50 presentations per region
2. **Use selective keys** - Request specific fields when possible
3. **Cache enumeration** - External tools should cache presentation catalog
4. **Poll efficiently** - Use appropriate intervals for state retrieval

## Future Enhancements (v2+)

### Write Operations

```php
// Potential v2 API
$this->abilities('set-presented-state', [
    'updates' => [
        'userCount' => 100,
    ]
])->then(function($response) {
    // Handle confirmation
});
```

**Challenges to solve**:
- Authorization (who can write)
- Validation (allowed values)
- Conflict resolution (concurrent writes)
- Rollback mechanism

### Reactive Streams

```php
// Potential v2 API
$this->abilities('subscribe-to-state', [
    'keys' => ['userCount'],
])->then(function($message) {
    // Receives updates when values change
});
```

**Challenges to solve**:
- WebSocket infrastructure
- Change detection mechanism
- Subscription lifecycle management
- Backpressure handling

### Computed Presentations

```php
// Potential v2 API
$builder->presentation(
    key: 'requestsPerSecond',
    label: 'Request Rate',
    intent: '...',
    compute: fn() => $this->get('totalRequests') / $this->get('uptime')
);
```

**Challenges to solve**:
- Dependency tracking (when to recompute)
- Caching strategy
- Error handling in computations

### Aggregations

```php
// Potential v3 API
$this->abilities('aggregate-state', [
    'metric' => 'requestsPerSecond',
    'window' => '5m',
    'aggregation' => 'avg'
]);
```

**Challenges to solve**:
- Time-series storage
- Aggregation algorithms
- Window management

## Comparison with Alternatives

### Alternative 1: No Presentation Feature (Status Quo)

**Pros**:
- No new code to maintain
- Maximum flexibility

**Cons**:
- Ad-hoc exposure patterns
- No discoverability
- No schema enforcement
- Duplicated monitoring logic

### Alternative 2: Generic Message-Based Approach

```php
// Hypothetical alternative
$this->dispatch(new StateSnapshot([
    'userCount' => 42,
    'progress' => 0.75,
]));
```

**Pros**:
- Simpler implementation
- No schema requirements

**Cons**:
- Not discoverable
- No metadata/intent
- No conditional visibility
- Breaks agentic pattern consistency

### Alternative 3: External State Accessor

```php
// Hypothetical alternative
$state = $region->getContext();
```

**Pros**:
- Direct access
- No middleware

**Cons**:
- Breaks encapsulation
- No schema validation
- No declarative contracts
- No discoverability

**Conclusion**: PresentationFeature provides the best balance of discoverability, structure, and consistency with existing patterns.

## Success Criteria

PresentationFeature will be considered successful when:

1. **Adoption** - Used in 3+ production machines
2. **Integration** - AI agent successfully uses presentations for decisions
3. **Documentation** - External tools discover presentations without docs
4. **Performance** - Enumeration < 100ms for typical machines
5. **Reliability** - Zero runtime errors in production (schema validation catches issues)

## Conclusion

PresentationFeature completes the agentic framework trilogy, providing the final piece for comprehensive machine introspection. By following established patterns (AbilitiesFeature, InteractionFeature) and enforcing schema validation (JsonSchemaFeature), it delivers structured observability without compromising type safety or serializability.

The feature is designed for gradual adoption, clear migration paths, and future extensibility while maintaining the spec-first, contract-driven philosophy of the Noem State Machine project.

## References

- specs/features/presentation.yaml - Complete acceptance criteria
- specs/features/abilities.yaml - Pattern reference for predicates and discovery
- specs/features/interaction.yaml - Pattern reference for message-based features
- specs/features/interaction-registry.yaml - Pattern reference for registry with YAML
- specs/features/json-schema.yaml - Dependency specifications
- src/Feature/Abilities/CLAUDE.md - Abilities documentation pattern
- src/Feature/Interaction/CLAUDE.md - Interaction documentation pattern
