# Noem State Machine - AI Agent Instructions

## 📋 Project Overview

Event-based finite state machines with hierarchical states, middleware systems, and extensive feature architecture. Built with PHP 8.4+, containerized development via DDEV, and **spec-driven development methodology**.

**Core Philosophy**: Specifications define WHAT (intent, behavior, API), not HOW (implementation, internals). Specs are executable contracts - when tests fail, fix code, never specs (unless user approves).

---

## 🚨 SPEC-FIRST DEVELOPMENT - ABSOLUTE RULE

**NEVER write code without specifications first.**

### Mandatory Workflow

```
User Request
    ↓
spec-planner agent → Creates YAML specs → User reviews
    ↓
User approves specs
    ↓
❓ ASK USER: "Should I proceed with implementing these specs?"
    ↓
If YES:
    ↓
HANDOVER (JSON payload)
    ↓
core-development-expert agent → RED: Write FAILING tests
    ↓
GREEN: Write code to pass tests → Quality checks
    ↓
Done

If NO: Stop (specs ready for later implementation)
```

### Critical Rules

1. ⛔ **NEVER write code without specs first**
   - No exceptions, no shortcuts
   - If specs don't exist, use `spec-planner` agent first

2. ⛔ **NEVER modify specs without user approval**
   - Specs are contracts, not suggestions
   - When tests fail → fix code, NEVER fix specs
   - If spec is wrong → ask user, don't assume

3. ⛔ **ONE SPEC = ONE TEST CLASS**
   - Maintain strict 1:1 mapping
   - Never consolidate multiple specs into one test
   - Never split one spec across multiple tests

4. ⛔ **Every bug = new spec FIRST**
   - Bug reported → create spec
   - Spec created → write failing test
   - Test failing → fix code

5. ⛔ **NEVER modify /vendor/ directory**
   - External dependencies are read-only
   - Never patch packages directly

---

## 🤖 Sub-Agent Usage

This project uses specialized sub-agents for different phases of development:

### spec-planner (Planning Phase)

**Use for**: Creating or modifying specifications

**When to invoke**:
- New features or enhancements
- Bug fixes (create spec for expected behavior)
- Refactoring that changes behavior
- Any task requiring new specifications

**Example**:
```
User: "Add a MessageFeature for request-response patterns"
→ Launch spec-planner agent to create specifications
```

**Output**: Approved spec files

**Next Step**: After user approves specs, ASK if they want to proceed with implementation

### core-development-expert (Implementation Phase)

**Use for**: Implementing code from approved specifications

**When to invoke**:
- After spec-planner completes AND user confirms they want to proceed with implementation
- When specs already exist and user wants them implemented

**Requires**: JSON handover payload containing:
- Spec file paths
- Component type
- Test directory
- Implementation files
- Critical notes

**Example**:
```
spec-planner completes → Generates handover payload
→ Launch core-development-expert with payload
```

**NEVER invoke without handover payload** - agent will refuse to proceed

### Handover Protocol

See `.claude/AGENT_HANDOVER_PROTOCOL.md` for complete handover protocol between agents.

**Key principle**: spec-planner creates specs, core-development-expert implements code. Clear separation of responsibilities.

---

## 🏗️ Architecture Overview

### Technology Stack

- **PHP 8.4+** - Modern PHP with strict types
- **DDEV** - Containerized development (prefix all commands with `ddev exec`)
- **Composer** - Dependency management
- **PHPUnit** - Testing framework with spec runners
- **Psalm** - Static analysis (level 1)
- **PHPCS** - Code style (PSR-12)

### Directory Structure

```
/
├── src/                    # Source code (✅ modify freely)
│   ├── Region.php         # State machine runtime
│   ├── RegionBuilder.php  # Fluent construction API
│   ├── Feature/           # Modular feature extensions
│   ├── Chains/            # Middleware pipelines
│   └── Middleware/        # ChainMail, Chain, Mesh
├── specs/                  # YAML specifications (⛔ USER APPROVAL REQUIRED)
│   ├── core/              # Region, RegionBuilder
│   ├── chain/             # Middleware
│   ├── features/          # Features
│   └── machines/          # End-to-end scenarios
├── tests/PHPUnit/         # Test suite (✅ create/modify)
│   ├── Unit/              # Unit tests
│   ├── Integration/       # Integration tests
│   └── E2E/               # End-to-end tests
├── machines/              # Example state machines
├── .claude/               # Agent configurations
│   └── agents/            # Sub-agent definitions
└── vendor/                # Dependencies (🚫 NEVER MODIFY)
```

### Key Commands

```bash
# Run all tests
ddev atlas

# Run spec-specific tests
ddev exec composer spec tests/PHPUnit/[TestClass].php

# Quality checks (style + static analysis + tests)
ddev exec composer quality

# Code style only
ddev exec composer cs

# Static analysis only
ddev exec composer psalm
```

---

## 🎛️ Feature System

Features extend RegionBuilder capabilities through wrapper pattern:

| Feature | Purpose | Critical Notes |
|---------|---------|----------------|
| **TransitionsFeature** | Automatic state transitions with guards | Default enabled |
| **ExtendedState** | Context data scoped to states/regions | ⚠️ Load before AsyncFeature |
| **AsyncFeature** | Coroutine-based async operations | Requires ExtendedState |
| **RegionLoader** | Load machines from YAML/arrays | Often loaded first |
| **Holon** | Complete machine bootstrap from YAML | One-liner setup |
| **OrthogonalRegions** | Parallel state execution | Hierarchical composition |
| **SubscriptionFeature** | Global event listeners with type filtering | For cross-region communication |
| **EventHooks** | Before/After hooks via attributes | Event interception |
| **NamedEvents** | Named subscriptions via attributes | Fine-grained filtering |
| **ComponentsFeature** | Entity/Component/System pattern | Attach behavior to states |
| **JsonSchemaFeature** | JSON schema validation | Context validation |
| **TemplateFeature** | Dynamic content generation | Mustache-style |
| **AiFeature** | AI integration | Claude API |

**⚠️ CRITICAL**: Feature order matters! Features wrap each other in LIFO order.

---

## 🔧 Context Helpers

When ExtendedState is enabled, callbacks have access to `$this` context helpers:

### Data Access
- `$this->get(string $key, mixed $default = null): mixed` - Retrieve context value
- `$this->set(string $key, mixed $value): void` - Store context value

### Dynamic Machine Loading (summon)

**Requires**: RegionLoader + ExtendedState

```php
$childRegion = $this->summon(string $filepath): Region
```

Dynamically loads a child state machine from YAML during callback execution.

**Key Points**:
- Returns `Region` (NOT `Runtime`) - you control execution
- Absolute paths used as-is, relative paths resolve against `loader.array.includes.basePath` or `getcwd()`
- Configure basePath via RegionBuilder: `->build(['loader' => ['array' => ['includes' => ['basePath' => '/custom/path']]]])`
- Fresh instance per call - store in context if reuse needed
- Use `subscribe`/`dispatch` for parent-child communication

**Example - Tool Composition**:
```php
->onAction('processing', function(object $t): \Generator {
    // Load child machine
    $generator = $this->summon('machines/machine-generator/holon.yml');
    yield;

    // Subscribe to child messages
    $generator->subscribe('generation.complete', fn($msg) =>
        $this->set('result', $msg->payload)
    );

    // Send request to child
    $generator->dispatch('generate.request', (object)[
        'payload' => ['description' => 'Create todo list'],
    ]);
    yield;

    // Execute child until complete
    while (!$generator->isComplete()) {
        $generator->run();
        yield;
    }
})
```

---

## ✅ Code Modification Rules

### ALLOWED - Internal Code
- ✅ Modify any code in `/src/` directory
- ✅ Create/modify tests in `/tests/PHPUnit/`
- ✅ Add new features, middleware, components
- ✅ Update internal dependencies

### PROHIBITED
- 🚫 Modify `/vendor/` directory
- 🚫 Patch external packages
- 🚫 Run commands without `ddev exec` prefix

### REQUIRES USER APPROVAL
- ⚠️ Modifying any YAML file in `/specs/`
- ⚠️ Changing acceptance criteria
- ⚠️ Removing or consolidating specs
- ⚠️ Breaking API changes

---

## 📚 Component-Specific Context

Some features and machines have their own CLAUDE.md files with specialized instructions:

**Check for component context**:
```bash
# Features
test -f src/Feature/{FeatureName}/CLAUDE.md && echo "EXISTS"

# Machines
test -f machines/{machine-name}/CLAUDE.md && echo "EXISTS"
```

**⚠️ Component-level CLAUDE.md files OVERRIDE general guidance.**

---

## 🎯 Quality Standards

### Before Marking Task Complete

- [ ] Specs exist and approved by user
- [ ] Test class maps 1:1 to spec
- [ ] Tests failed initially (red phase verified)
- [ ] All tests now pass (green phase)
- [ ] `ddev exec composer quality` passes
- [ ] No modifications to `/vendor/`
- [ ] No spec changes without approval

### Code Style
- PSR-12 compliance
- Type declarations on all functions/methods
- Descriptive variable names
- Comprehensive docblocks for public APIs

### Testing
- Unit tests for isolated logic
- Integration tests for feature interactions
- 100% coverage of public APIs
- Edge cases and error conditions tested

### Static Analysis
- Psalm level 1 (strictest)
- No `@psalm-suppress` without justification
- Generic types properly annotated

---

## 🔍 Development Workflow Example

### Scenario: User wants new feature

1. **User**: "Add a CachingFeature that memoizes state computations"

2. **You**: Launch `spec-planner` agent
   - spec-planner creates YAML specs in `/specs/features/caching.yaml`
   - Presents to user for approval
   - User approves

3. **spec-planner**: Generates handover payload
   ```json
   {
     "agent": "core-development-expert",
     "handover_type": "approved_specification",
     "spec_files": ["specs/features/caching.yaml"],
     "component_type": "feature",
     "test_directory": "tests/PHPUnit/Unit/Feature/Caching/",
     "implementation_files": ["src/Feature/Caching/CachingFeature.php"],
     "summary": "CachingFeature - Memoize state computations",
     "critical_notes": ["Consider cache invalidation strategy"]
   }
   ```

4. **You**: Launch `core-development-expert` with handover payload
   - Validates payload
   - **RED PHASE**: Writes tests that FAIL (no implementation code yet)
   - Verifies tests fail for correct reasons
   - **GREEN PHASE**: Writes minimal code to make tests pass
   - Runs quality checks
   - Reports completion

### Scenario: Specs already exist

1. **User**: "The specs are in specs/core/guards.yaml - implement this"

2. **You**: Create handover payload from existing spec
   - Read spec file
   - Build handover payload
   - Launch core-development-expert with payload

---

## 🚫 Anti-Patterns to Avoid

❌ Writing code before specs exist
❌ Modifying specs when tests fail
❌ Skipping spec-planner for new features
❌ Invoking core-development-expert without handover
❌ Running commands outside DDEV container
❌ Consolidating or splitting specs without approval
❌ Modifying vendor directory

---

## 📖 Additional Resources

- `.claude/AGENT_HANDOVER_PROTOCOL.md` - Complete handover protocol
- `.claude/agents/spec-planner.md` - Specification agent definition
- `.claude/agents/core-development-expert.md` - Implementation agent definition
- `README.md` - Project setup and quick start
