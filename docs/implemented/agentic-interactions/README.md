# Agentic Interaction Patterns

**Status**: ✅ Implemented
**Created**: 2026-01-03
**Implemented**: 2026-01-09
**Type**: Feature + Message Protocol

> **Note**: InteractionFeature is now fully implemented. See `specs/features/interaction.yaml` for the complete specification and `src/Feature/Interaction/` for the implementation.

---

## Overview

This proposal defines a **minimal set of standardized interaction patterns** for autonomous state machines to communicate with external agents, supervisors, and orchestration frameworks.

These patterns complement the existing **Abilities API** (inward business logic invocation) by providing a **soft contract for outward interactions** (requesting information, decisions, and input from external entities).

**Core Principle**: Just as "abilities" enable external agents to invoke business logic on a machine, **interaction patterns** enable machines to request information and decisions from external agents.

---

## Problem Statement

### Current Gap

The state machine framework has robust **inward communication** through the Abilities API, but lacks standardized **outward communication** patterns for:

- ❌ Requesting user confirmations (yes/no)
- ❌ Presenting choices (select one, select many)
- ❌ Gathering free-form input (prompts)
- ❌ Coordinating with supervisor agents
- ❌ Negotiating configuration with orchestration frameworks

Each machine currently invents its own ad-hoc interaction patterns.

### Requirements

When machines run within orchestration frameworks (CLI, web UI, API, agent supervisors), the framework needs to:

1. **Detect** when a machine needs external input
2. **Understand** what type of interaction is needed
3. **Present** appropriate interface (buttons, dropdowns, text fields, agent prompts)
4. **Deliver** responses back to machine
5. **Track** interaction state (pending, answered, timeout)

---

## Proposed Solution

### Four Fundamental Patterns

| Pattern | Purpose | Response Type | Use Cases |
|---------|---------|---------------|-----------|
| **Confirm** | Yes/No decision | `boolean` | Approve action, verify understanding |
| **Select** | Choose one option | `string` | Pick from list, configuration choice |
| **Choice** | Choose multiple options | `array<string>` | Multi-select features, tags |
| **Prompt** | Free-form input | `string` | Gather text, ask questions |

### Design Principles

1. ✅ **Built on MessageFeature** - Leverage existing correlation infrastructure
2. ✅ **JSON-Serializable** - Can persist, transmit, log interactions
3. ✅ **Framework-Agnostic** - Works in CLI, web UI, API, agent-to-agent
4. ✅ **Type-Safe** - Response validation via schemas
5. ✅ **Timeout-Aware** - Support asynchronous interaction with deadlines
6. ✅ **Extensible** - Easy to add new patterns (file upload, date picker, etc.)

---

## Architecture

### Message Hierarchy

```
Message (abstract)
  ├─ AbilityMessage          # Existing - inward invocation
  └─ InteractionRequest      # NEW - outward interaction
       ├─ ConfirmRequest     # Yes/No
       ├─ SelectRequest      # Choose one
       ├─ ChoiceRequest      # Choose multiple
       └─ PromptRequest      # Free text

InteractionResponse (abstract)
  ├─ ConfirmResponse
  ├─ SelectResponse
  ├─ ChoiceResponse
  └─ PromptResponse
```

### Core API

**In Machine Code**:
```php
// Request confirmation
$confirmed = yield from $this->interact(new ConfirmRequest(
    question: 'Deploy to production?',
    context: 'This will affect 10,000 users',
    defaultValue: false
));

// Select from options
$model = yield from $this->interact(new SelectRequest(
    question: 'Which AI model?',
    options: [
        'claude' => new SelectOption('Claude Sonnet', 'High quality'),
        'qwen' => new SelectOption('Qwen Coder', 'Fast, local'),
    ]
));

// Multi-select
$features = yield from $this->interact(new ChoiceRequest(
    question: 'Enable features?',
    options: [
        'async' => new ChoiceOption('Async', 'Coroutines'),
        'ai' => new ChoiceOption('AI', 'LLM integration'),
    ],
    minSelections: 1
));

// Free text
$description = yield from $this->interact(new PromptRequest(
    question: 'Describe the task',
    placeholder: 'Enter details...',
    multiline: true
));
```

**In Framework Code**:
```php
// Subscribe to all interaction requests
$region->on(function(InteractionRequest $request, ?Region $source) {
    // Render appropriate UI based on $request->getType()
    // Collect user input
    // Send response back via MessageFeature correlation

    $response = match($request->getType()) {
        'confirm' => $this->handleConfirm($request),
        'select' => $this->handleSelect($request),
        'choice' => $this->handleChoice($request),
        'prompt' => $this->handlePrompt($request),
    };

    $source->notificationChain->call(new Notify($source, $response));
});
```

---

## Documents

### 📋 [interaction-patterns.md](./interaction-patterns.md) - Main Proposal

**Primary document** covering:
- Detailed pattern specifications (Confirm, Select, Choice, Prompt)
- InteractionFeature implementation
- Framework adapter patterns (CLI, Web API)
- Integration with MessageFeature and BoundAccess
- Extension points for custom patterns
- Testing strategy
- Implementation phases (4 phases, ~90 hours total)
- Security considerations
- Open questions and decisions needed

**Read this first** for comprehensive understanding.

---

## Quick Start

### Example: Machine-Agent Interactive Questioning

**Before** (Ad-hoc implementation):
```php
->onAction('ask_question', function(object $t): \Generator {
    echo "What features do you want?\n";
    $input = trim(fgets(STDIN));
    // Custom parsing, no validation, framework-specific
})
```

**After** (Standardized pattern):
```php
->onAction('ask_question', function(object $t): \Generator {
    $features = yield from $this->interact(new ChoiceRequest(
        question: 'What features do you want?',
        options: [
            'async' => new ChoiceOption('Async Operations'),
            'ai' => new ChoiceOption('AI Integration'),
            'persistence' => new ChoiceOption('State Persistence'),
        ],
        minSelections: 1
    ));

    $this->set('selected_features', $features);
})
```

**Benefits**:
- ✅ Type-safe, validated responses
- ✅ Framework-agnostic (works in CLI, web, API)
- ✅ JSON-serializable for logging/debugging
- ✅ Automatic correlation handling
- ✅ Rich metadata (descriptions, defaults, constraints)

---

## Use Cases

### 1. Autonomous Agents Coordinating

**Supervisor agent** orchestrates **worker agents**:

```php
// Worker agent requests confirmation from supervisor
$approved = yield from $this->interact(new ConfirmRequest(
    question: 'Execute high-risk operation?',
    context: json_encode(['operation' => 'delete_database', 'impact' => 'high'])
));

// Supervisor listens and applies policy
$supervisorRegion->on(function(ConfirmRequest $req, Region $worker) {
    // Check policy, logs, user permissions
    $approved = $this->policy->evaluate($req->context);

    $worker->notificationChain->call(new Notify(
        $worker,
        $req->createResponse(ConfirmResponse::class, ['confirmed' => $approved])
    ));
});
```

### 2. CLI Interactive Workflows

```php
// Machine
$backend = yield from $this->interact(new SelectRequest(
    question: 'Choose database',
    options: [
        'mysql' => new SelectOption('MySQL', 'Traditional RDBMS'),
        'postgres' => new SelectOption('PostgreSQL', 'Advanced features'),
    ]
));

// CLI Framework auto-renders
// ? Choose database (Use arrow keys)
//   > MySQL - Traditional RDBMS
//     PostgreSQL - Advanced features
```

### 3. Web UI Integration

```php
// Machine code (unchanged)
$confirmed = yield from $this->interact(new ConfirmRequest(
    question: 'Delete account?',
    context: 'This action is permanent'
));

// Web API exposes pending interactions
GET /machines/{id}/interactions/pending
{
  "interactions": [{
    "correlationId": "550e8400-...",
    "type": "confirm",
    "question": "Delete account?",
    "context": "This action is permanent",
    "defaultValue": false
  }]
}

// User responds via API
POST /machines/{id}/interactions/550e8400-.../respond
{
  "confirmed": true
}
```

### 4. Agent-to-Agent Negotiation

```php
// Generator agent asks planner agent for guidance
$complexity = yield from $this->interact(new SelectRequest(
    question: 'What complexity level for this task?',
    options: [
        'simple' => new SelectOption('Simple', '1-3 states'),
        'moderate' => new SelectOption('Moderate', '4-8 states'),
        'complex' => new SelectOption('Complex', '9+ states'),
    ]
));

// Planner agent (AI-powered) evaluates and responds
$plannerRegion->on(function(SelectRequest $req, Region $generator) {
    $analysis = $this->ai->analyze($req->question, $req->options);
    $selected = $analysis['recommended_option'];

    $generator->notificationChain->call(new Notify(
        $generator,
        $req->createResponse(SelectResponse::class, ['selectedKey' => $selected])
    ));
});
```

---

## Implementation Roadmap

### Phase 1: Core Messages (2-3 weeks)

- Define base `InteractionRequest` and `InteractionResponse`
- Implement 4 core patterns (Confirm, Select, Choice, Prompt)
- Add JSON serialization
- Unit tests

### Phase 2: Feature Integration (3-4 weeks)

- Create `InteractionFeature` class
- Bind `$this->interact()` to BoundAccess
- Integrate with MessageFeature correlation
- Integration tests

### Phase 3: Framework Adapters (2-3 weeks)

- CLI adapter (symfony/console)
- Web API adapter (REST)
- Documentation and examples

### Phase 4: Extensions (1-2 weeks)

- Timeout handling
- Advanced patterns (file upload, date picker)
- Performance optimization
- Security hardening

**Total Effort**: ~90 hours / 8-12 weeks

---

## Open Questions

### 1. Timeout Handling

**Question**: How should timeouts be handled?

**Options**:
- A. Throw exception (forces explicit handling) ← **Recommended**
- B. Return default value (graceful degradation)
- C. Return "timed out" response (explicit but verbose)

**Decision needed by**: Phase 2 start

---

### 2. Persistence During Interactions

**Question**: Can we serialize a machine waiting for interaction response?

**Challenge**: Response handler closures may not serialize

**Options**:
- A. Prohibit during MVP, document limitation ← **Recommended**
- B. Store interaction state separately (complex)
- C. Require named handlers only (restrictive)

**Decision needed by**: Phase 2 completion

---

### 3. Nested Interactions

**Question**: Should nested interactions be supported?

**Example**:
```php
$action = yield from $this->interact(new SelectRequest(...));

if ($action === 'delete') {
    // Nested confirmation
    $confirmed = yield from $this->interact(new ConfirmRequest(...));
}
```

**Recommendation**: Yes - already works with correlation system

---

## Success Metrics

1. **Adoption**: 3+ machines use InteractionFeature within 3 months
2. **Framework Support**: 2+ framework adapters implemented
3. **Code Reduction**: 30% less custom interaction code
4. **Developer Satisfaction**: Positive API feedback
5. **Performance**: <1ms overhead per interaction

---

## Related Resources

### Internal

- [Abilities API Proposal](../abilities-api/abilities-api.md) - Inward invocation (complement)
- [MessageFeature Spec](../../specs/features/message.yaml) - Correlation infrastructure
- [SubscriptionFeature Spec](../../specs/features/subscription.yaml) - Event system
- [Machine-Agent Proposal](../machine-agent.md) - Primary use case
- [ExtendedState CLAUDE.md](../../src/Feature/ExtendedState/CLAUDE.md) - BoundAccess integration

### External

- [Inquirer.js](https://github.com/SBoudrias/Inquirer.js) - CLI prompts (inspiration)
- [Laravel Prompts](https://laravel.com/docs/prompts) - Similar pattern in PHP
- [GitHub CLI](https://cli.github.com/) - Interactive CLI best practices
- [Discord Interactions](https://discord.com/developers/docs/interactions/receiving-and-responding) - Message-based interaction model

---

## Key Insights

### Why This Matters for Agentic Systems

1. **Standardized Coordination**: Agents can negotiate without custom protocols
2. **Framework Independence**: Same machine runs in CLI, web, or agent swarm
3. **Debuggability**: All interactions logged as JSON messages
4. **Type Safety**: Schema validation prevents miscommunication
5. **Composability**: Interactions work with existing Message/Subscription infrastructure

### Comparison: Abilities vs. Interactions

| Aspect | Abilities API (Inward) | Interaction Patterns (Outward) |
|--------|------------------------|--------------------------------|
| **Direction** | External → Machine | Machine → External |
| **Purpose** | Invoke business logic | Request information/decisions |
| **Initiated By** | External agent/user | Machine itself |
| **Example** | `$agent->abilities('processOrder', $data)` | `$confirmed = yield from $this->interact(...)` |
| **Use Case** | "Do this task" | "I need to know..." |

**Together**, they form a complete bidirectional communication protocol for agentic systems.

---

## Contributing

When contributing to this proposal:

1. ✅ Follow spec-driven development methodology
2. ✅ All patterns extend `Message` for correlation support
3. ✅ JSON-serializable for persistence/transmission
4. ✅ Framework-agnostic design
5. ✅ Document all design decisions
6. ✅ Add comprehensive tests

---

## Next Steps

1. **Review & Feedback** (Week 1)
   - Share with maintainers and community
   - Gather feedback on API design
   - Refine based on input

2. **Create Specifications** (Week 2)
   - Follow spec-driven development
   - Define acceptance criteria
   - Create YAML specs for each pattern

3. **Implementation** (Weeks 3-10)
   - Phase 1: Core messages
   - Phase 2: Feature integration
   - Phase 3: Framework adapters
   - Phase 4: Extensions

4. **Documentation** (Week 11)
   - Feature CLAUDE.md
   - Usage examples
   - Migration guide

5. **Validation** (Week 12)
   - Real-world testing
   - Performance benchmarks
   - Security audit

---

**Last Updated**: 2026-01-03
**Status**: Draft - Awaiting review and feedback
**Contact**: Open GitHub issue for questions/discussion
**Next Milestone**: Specification creation (pending approval)
