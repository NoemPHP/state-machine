# Weave - Agentic Tool Selection and Execution

**Status**: Implemented
**Created**: 2025-12-26
**Related**: [ai-enhancements.md](./ai-enhancements.md)

## Overview

Extend AiFeature with a `$this->weave()` context helper that provides **agentic operation capabilities** - AI-powered tool enumeration, selection, and execution. This enables state machines to delegate complex multi-step operations to AI by describing intent rather than explicit control flow.

---

## Motivation

Current AiFeature provides direct AI operations (`complete`, `capture`) but requires developers to explicitly orchestrate tool usage. AbilitiesFeature provides discoverable, schema-validated operations but requires manual selection and invocation.

**Gap**: No mechanism for AI to autonomously:
1. Discover available tools (abilities)
2. Select appropriate tools based on natural language intent
3. Execute selected tools with AI-generated parameters
4. Aggregate and return structured results

**Use Case Example**:
```php
// Current approach (manual orchestration)
$abilities = yield $this->abilities('enumerate-abilities');
$userInfo = yield $this->abilities('get-user-info', ['userId' => 123]);
$preferences = yield $this->abilities('get-preferences', ['userId' => 123]);
$recommendation = yield $this->capture("Generate recommendation based on: " . json_encode(compact('userInfo', 'preferences')));

// Proposed approach (agentic)
$result = yield $this->weave("Generate a personalized recommendation for user 123");
// AI automatically: enumerates abilities, calls get-user-info + get-preferences, generates recommendation
```

---

## Proposed API

### Context Helper Method

```php
$this->weave(string $intent, array $options = []): Generator
```

**Parameters**:
- `$intent` (string, required): Natural language description of the desired operation
  - Example: "Find all users matching criteria X and send them notification Y"
  - Example: "Analyze current state and determine next action"

- `$options` (array, optional): Configuration options
  - `tools` (array|null): Explicit tool scope (ability name patterns), defaults to all available abilities
  - `maxIterations` (int): Maximum tool selection/execution cycles, default 3
  - `backend` (string|null): AI backend override (openai, anthropic, ollama)
  - `complexity` (string|null): Capability-based backend selection complexity level
  - `context` (string|null): Capability-based backend selection context window requirement
  - `schema` (array|null): JSON Schema for structured result validation (applied to final output)

**Returns**: `Generator` yielding associative array

---

## Return Value Structure

Simple associative array with the following keys:

```php
[
    'selectedTools' => ['ability-1', 'ability-2'],  // Array of selected ability names
    'toolCalls' => [                                 // Array of tool call results
        [
            'ability' => 'ability-1',
            'parameters' => [...],
            'result' => mixed,
            'success' => true,
            'error' => null|string
        ],
        // ...
    ],
    'result' => mixed,                               // Final AI-synthesized result
    'iterations' => [                                // Array of iteration logs
        [
            'iteration' => 1,
            'tools' => ['ability-1'],
            'reasoning' => 'AI explanation...',
            'results' => [...]
        ],
        // ...
    ],
    'reasoning' => 'AI explanation of tool selection'
]
```

**Rationale**: Uses primitives aligned with existing codebase patterns (RequestBuilder returns arrays, abilities use simple associative arrays). No DTOs needed unless a contract requires it.

---

## Integration Points

### 1. **AbilitiesFeature Integration**

`weave()` uses `enumerate-abilities` to discover available tools:

```php
// Internal implementation sketch
$abilities = yield $this->abilities('enumerate-abilities');
$toolDescriptions = array_map(fn($ability) => [
    'name' => $ability['name'],
    'description' => $ability['description'],
    'parameters' => $ability['parameterSchema']
], $abilities);
```

**Dependency**: Requires AbilitiesFeature to be loaded

### 2. **AiFeature Backend Selection**

Leverages existing capability-based or explicit backend selection:

```php
// Uses ModelPool for backend selection based on complexity/context
$backend = $this->resolveBackend($options['backend'] ?? null, $options['complexity'] ?? null, $options['context'] ?? null);
```

### 3. **Template Integration** (Optional Future Enhancement)

Could support template helper syntax:

```mustache
{{#weave "Generate user report for current context"}}
  {{#each toolCalls}}
    - {{abilityName}}: {{result}}
  {{/each}}

  Final Result: {{aggregatedResult}}
{{/weave}}
```

---

## Algorithm Flow

### Phase 1: Tool Enumeration

1. Invoke `enumerate-abilities` to get available tools
2. Filter by `options.tools` pattern if specified
3. Extract tool signatures (name, description, parameterSchema)

### Phase 2: Agentic Planning (AI-Powered)

Construct planning prompt:

```
You are an agentic system with access to the following tools:

[Tool definitions with schemas]

User Intent: {$intent}

Your task:
1. Analyze the intent
2. Select the minimal set of tools needed
3. Determine the order of execution
4. Generate parameters for each tool call

Respond with a JSON array of tool invocations:
{
  "reasoning": "explanation of selection",
  "tools": [
    {"ability": "tool-name", "parameters": {...}},
    ...
  ]
}
```

Use `$this->capture()` with schema for structured response

### Phase 3: Tool Execution

For each selected tool:

```php
$toolCall = [
    'ability' => $tool['ability'],
    'parameters' => $tool['parameters'],
    'result' => null,
    'success' => false,
    'error' => null
];

try {
    $message = yield $this->abilities($tool['ability'], $tool['parameters']);
    $result = yield; // Wait for response via message correlation
    $toolCall['result'] = $result;
    $toolCall['success'] = true;
} catch (\Exception $e) {
    $toolCall['error'] = $e->getMessage();
}
```

### Phase 4: Iteration (Optional)

If `maxIterations > 1`, AI can request additional tool calls based on previous results:

```
Previous results: [...]
Do you need additional tool calls to complete the intent?
```

### Phase 5: Result Aggregation

Final AI synthesis prompt:

```
Tool call results:
[JSON of all tool calls and results]

Original intent: {$intent}

Synthesize these results into a final answer addressing the user's intent.
```

Use `$this->capture()` or `$this->complete()` to generate final result

---

## Error Handling

### Tool Invocation Failures

- Individual tool failures captured in `toolCalls[n]['error']`
- Execution continues for remaining tools
- AI receives error context for adaptation in next iteration

### AI Selection Failures

- Invalid ability names → logged in iteration, skipped
- Schema validation failures → logged, skipped
- No tools selected → return empty result with reasoning

### Iteration Limits

- Hard cap at `maxIterations` prevents infinite loops
- Each iteration logged in returned array's `iterations` key

---

## Configuration via AiConfigFeature

Extend AiConfigFeature to accept weave-specific defaults:

```php
new AiConfigFeature([
    'credentials' => [...],
    'modelPool' => [...],
    'weave' => [
        'defaultMaxIterations' => 3,
        'defaultBackend' => 'anthropic',  // Claude excels at tool use
        'defaultComplexity' => 'high',    // Agentic ops need reasoning
        'promptTemplates' => [
            'planning' => '...',          // Custom planning prompt
            'aggregation' => '...'        // Custom aggregation prompt
        ]
    ]
])
```

---

## Security Considerations

### Ability Access Control

`weave()` respects AbilitiesFeature predicates:

- Conditional abilities only exposed when predicate returns true
- `enumerate-abilities` already filters by availability
- No privilege escalation - weave runs in same context as caller

### Parameter Injection Risks

AI-generated parameters may contain unexpected values:

**Mitigation**:
- AbilitiesFeature schema validation applies to all tool calls
- Option to restrict `weave()` to specific tool subsets via `options.tools`
- Iteration logs provide audit trail

### Prompt Injection

User-controlled `$intent` could manipulate AI behavior:

**Mitigation**:
- Clearly delimited sections in prompts (system vs user intent)
- Schema-constrained AI responses (structured output only)
- Iteration limit prevents runaway execution

---

## Performance Characteristics

### Cost Implications

- **Planning call**: 1 AI request per iteration (with tool schemas in context)
- **Tool execution**: N ability invocations (synchronous or async via AsyncFeature)
- **Aggregation call**: 1 AI request with all results

**Total**: 2-6 AI requests depending on iterations

### Latency Optimization

- Use `complexity='low'` for simple tasks to route to faster models
- AsyncFeature integration allows parallel tool execution
- Cache `enumerate-abilities` results per state

### Context Window Management

- Large tool sets may exceed context limits
- Use `options.tools` filtering to reduce scope
- Consider chunking tool descriptions if >100 abilities

---

## Backward Compatibility

- New context helper, no breaking changes
- Requires AbilitiesFeature (new dependency check)
- AiConfigFeature changes are additive (optional `weave` key)

---

## Testing Strategy

### Unit Tests

1. `WeaveRegistersContextMethodTest.php` - Context method registration
2. `WeaveAcceptsIntentTest.php` - Required intent parameter
3. `WeaveAcceptsOptionsTest.php` - Optional configuration
4. `WeaveReturnsGeneratorTest.php` - Generator return type
5. `WeaveEnumeratesToolsTest.php` - Calls enumerate-abilities
6. `WeaveSelectsToolsViaAiTest.php` - AI-powered selection
7. `WeaveExecutesSelectedToolsTest.php` - Tool invocation
8. `WeaveAggregatesResultsTest.php` - Final synthesis
9. `WeaveRespectsMaxIterationsTest.php` - Iteration limiting
10. `WeaveHandlesToolFailuresTest.php` - Error resilience

### Integration Tests

1. `WeaveAbilitiesIntegrationTest.php` - End-to-end with AbilitiesFeature
2. `WeaveAsyncIntegrationTest.php` - Async tool execution
3. `WeaveCapabilitySelectionTest.php` - ModelPool integration
4. `WeaveIterativeRefinementTest.php` - Multi-iteration scenarios
5. `WeaveSecurityTest.php` - Predicate filtering, schema validation

---

## Spec File Location

Add to `specs/features/ai.yaml` under new feature group:

```yaml
- name: agentic-weave
  description: AI-powered tool selection and execution via weave() context helper
  specs:
    - acceptanceCriteria: AiFeature registers weave() method in ExtendedState context
      criticality: contract
      intent: Provides agentic operation capabilities through context API
      test: vendor/bin/phpunit tests/PHPUnit/Unit/Feature/Ai/Weave/RegistersWeaveMethodTest.php

    # ... (additional specs following test strategy)
```

---

## Open Questions

1. **Result Schema Enforcement**: Should `options.schema` validate final `result` key or entire return array?
2. **Tool Call Parallelism**: Should tools be executed in parallel (via AsyncFeature) or sequentially?
3. **Intermediate Results Visibility**: Should AI receive raw tool results or summarized versions?
4. **Conversation Memory**: Should multi-iteration weave maintain conversation history for context?
5. **Tool Call Limits**: Should there be a max tool calls per iteration (e.g., max 5 tools per cycle)?

---

## Example Usage Scenarios

### Scenario 1: Data Aggregation

```php
// State has abilities: get-user-profile, get-order-history, get-preferences
$result = yield $this->weave("Prepare a complete customer profile for user 456");

// AI automatically:
// 1. Calls get-user-profile(userId: 456)
// 2. Calls get-order-history(userId: 456)
// 3. Calls get-preferences(userId: 456)
// 4. Synthesizes into structured profile

echo $result['result']; // Complete customer profile
print_r($result['selectedTools']); // ['get-user-profile', 'get-order-history', 'get-preferences']
```

### Scenario 2: Conditional Workflow

```php
// Abilities: check-inventory, place-order, notify-backorder
$result = yield $this->weave("Process order for product SKU-123, quantity 5");

// AI reasoning:
// - First check-inventory(sku: SKU-123)
// - If available: place-order(sku: SKU-123, qty: 5)
// - If unavailable: notify-backorder(sku: SKU-123, qty: 5)

foreach ($result['toolCalls'] as $call) {
    echo "{$call['ability']}: " . ($call['success'] ? 'OK' : $call['error']) . "\n";
}
```

### Scenario 3: Iterative Refinement

```php
$result = yield $this->weave(
    "Find the best product recommendation based on user preferences and current promotions",
    ['maxIterations' => 3]
);

// Iteration 1: get-user-preferences → learns user likes electronics
// Iteration 2: get-active-promotions → finds electronics sale
// Iteration 3: get-top-rated-products(category: electronics) → finds candidates
// Aggregation: Synthesizes best match

echo "Final recommendation: " . $result['result'];
echo "Reasoning: " . $result['reasoning'];
```

---

## Implementation Phases

### Phase 1: Core Infrastructure (MVP)

- Context method registration (`$this->weave()`)
- Single-iteration flow (no multi-iteration)
- Basic planning and aggregation prompts
- Simple array return structure

### Phase 2: Iteration Support

- Multi-iteration loop with result feedback
- `maxIterations` enforcement
- Iteration logging in return array

### Phase 3: Advanced Features

- Template helper support (`{{#weave}}` - optional)
- AiConfigFeature integration for defaults
- Custom prompt templates
- Performance optimizations (caching, parallel execution)

### Phase 4: Developer Experience

- Logging/debugging tools
- Iteration visualization
- Error diagnostics
- Best practices documentation

---

## Related Work

- **OpenAI Function Calling**: Similar tool selection mechanism, but stateless
- **LangChain Agents**: Chain-based agentic workflows, heavier framework
- **AutoGPT**: Full autonomous agents, more complex than needed here
- **Claude Tool Use**: Anthropic's native tool use API (directly applicable)

**Noem Advantage**: Tight integration with state machine lifecycle, AbilitiesFeature schema validation, async cooperation

---

## Alternatives Considered

### Alternative 1: Manual Tool Selection Helper

```php
$tools = yield $this->selectTools("intent", $abilities); // Just selection, no execution
```

**Rejected**: Doesn't reduce orchestration burden enough

### Alternative 2: Hardcoded Workflows

```php
$this->workflow('customer-profile', ['userId' => 123]);
```

**Rejected**: Not flexible, requires predefined workflows

### Alternative 3: External Agent Framework

Use LangChain/AutoGPT via external integration

**Rejected**: Heavy dependencies, poor state machine integration

---

## Success Criteria

1. **Functional**: `weave()` can successfully select and execute abilities based on natural language intent
2. **Reliable**: Handles tool failures gracefully, respects iteration limits
3. **Secure**: No privilege escalation, schema validation applies
4. **Performant**: Completes typical 3-tool workflows in <5s (excluding AI latency)
5. **Discoverable**: Clear examples in documentation, intuitive API
6. **Tested**: >90% coverage of core weave logic
7. **Primitive-First**: Uses simple arrays and existing helpers, no unnecessary DTOs

---

## Next Steps

1. **Review**: Gather feedback on API design and scope
2. **Spec Creation**: Use `spec-planner` agent to create detailed YAML specs
3. **Prototype**: Implement Phase 1 (MVP) for validation
4. **Iterate**: Refine based on real-world usage patterns
5. **Document**: Create comprehensive usage guide with examples