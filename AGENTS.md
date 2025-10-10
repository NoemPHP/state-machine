[comment]:# (AGENTS.MD MAINTENANCE GUIDE FOR AI AGENTS)
[comment]:# (Purpose: This file is the primary onboarding and reference document for AI agents working on the Noem State Machine codebase.)
[comment]:# (Target: AI agents starting new sessions or looking up specific implementation details.)
[comment]:# (Philosophy: Information density over verbosity. Critical info first. Examples over prose. Tables over paragraphs.)
[comment]:# (Current length: ~350 lines. Keep under 400 lines. If adding content, remove something of equal size.)

[comment]:# (STRUCTURE: 3-Layer Information Architecture)
[comment]:# (Layer 1 [Lines 1-120]: ESSENTIALS - What agents need in first 60 seconds)
[comment]:# (  - Cardinal Rules, Quick Workflow, Essential Commands, Common Errors)
[comment]:# (  - This section should NEVER exceed 120 lines)
[comment]:# (  - Add new essentials only if they're needed in >50% of sessions)
[comment]:# (Layer 2 [Lines 121-280]: CORE CONCEPTS - Methodology and architecture)
[comment]:# (  - Spec-driven development workflow, project architecture, code conventions)
[comment]:# (  - Balance theory with practice - every concept needs an example)
[comment]:# (Layer 3 [Lines 281-end]: REFERENCE - Lookup information)
[comment]:# (  - Directory structure, commands, checklists, examples)
[comment]:# (  - Organized for quick scanning, heavy use of tables)

[comment]:# (STYLE GUIDELINES:)
[comment]:# (1. Use tables for: commands, decisions, comparisons, checklists)
[comment]:# (2. Use code blocks for: examples, patterns, actual code)
[comment]:# (3. Use lists for: steps, requirements, options)
[comment]:# (4. Avoid: long paragraphs, motivational content, redundant explanations)
[comment]:# (5. Every technical detail must have ✅/❌ example showing correct/incorrect usage)
[comment]:# (6. Emoji sparingly: 🚨 for critical, ✅ for correct, ❌ for wrong, 📋 for checklists)

[comment]:# (MAINTENANCE PRINCIPLES:)
[comment]:# (- When adding: Remove equal amount of less-critical content or consolidate)
[comment]:# (- When updating: Update ALL related occurrences - no redundancy)
[comment]:# (- When fixing: Add to "Common Errors" table, not scattered through doc)
[comment]:# (- Keep command examples DRY - reference table, don't repeat)
[comment]:# (- Archive removed content to AGENTS_ARCHIVE.md if might be useful later)

# Noem State Machine - AI Agent Guide

Event-based finite state machines with hierarchical states, middleware, and feature system.

---

[comment]:# (=== LAYER 1: ESSENTIALS - Critical information for immediate use ===)
[comment]:# (This section must be scannable in 60 seconds. Dense, actionable, no fluff.)

## 🚨 START HERE: Essential Rules

1. **NEVER write code without specs first**
2. **NEVER modify specs without user approval**
3. **ALWAYS test iteratively during implementation**
4. **SPECS ARE THE CONTRACT** - fix code, not specs when tests fail
5. **ONE SPEC = ONE TEST CLASS** - maintain 1:1 mapping

## Quick Workflow

```
User Request → Plan Specs (get approval) → Create Tests (red) → Implement (iterate + test) → All Pass (green) → Done
```

## Essential Commands

| Task                    | Command                                                                                  | When to Use            |
|-------------------------|------------------------------------------------------------------------------------------|------------------------|
| Run single test         | `ddev exec vendor/bin/phpunit tests/PHPUnit/Unit/[Component]/[Test].php`                 | After each code change |
| Run spec suite          | `ddev exec machines/middleware-test-runner/run.sh --spec=specs/[group]/[component].yaml` | Before completion      |
| Atlas (full regression) | **`ddev atlas`**                                                                         | After completion       |
| Full quality check      | `ddev exec composer quality`                                                             | Before commit          |

**Note**: All commands must be prefixed with `ddev exec` to run inside containers.

[comment]:# (Common Errors table: Add new errors here, not scattered in text. Format: Error | Cause | Solution)
[comment]:# (Keep to most frequent errors only - if error occurs <5% of sessions, it doesn't belong here)

## Common Errors & Solutions

| Error                                 | Cause                                          | Solution                                                 |
|---------------------------------------|------------------------------------------------|----------------------------------------------------------|
| `Service 'X' not found`               | ChainMail service not registered or wrong type | Use proper return type in factory: `fn(): MyType => ...` |
| `Required Parameter 0 not declared`   | Guard function missing trigger param           | Add parameter: `fn(object $trigger): bool => true`       |
| `Undefined constant MetaType::Region` | Wrong MetaType usage                           | Use `ContextMetaType::get()` instead                     |
| Tests fail after "working" change     | Specs define the contract                      | Fix your code, not the tests                             |

---

[comment]:# (=== LAYER 2: CORE CONCEPTS - Methodology and implementation patterns ===)
[comment]:# (Balance: Each concept should have theory + practical example + common pitfall)
[comment]:# (Target audience: Agent planning implementation, needs to understand "why" and "how")

## Spec-Driven Development

**Philosophy**: Specs are executable contracts that prevent architectural drift across AI agent sessions. They define *what* the code must do; implementation is *how* it does it.

[comment]:# (4-Stage Workflow: Keep this concise. Each stage = purpose + key commands + deliverable)
[comment]:# (If workflow changes, update both here and "Quick Workflow" diagram in Layer 1)

### The 4-Stage Workflow

#### 1. Feature Planning (MANDATORY before coding)
```bash
# Review existing specs
cat specs/[group]/[component].yaml
grep -r "[related-concept]" specs/

# Plan changes
# - Which specs are affected?
# - Any conflicts or overlaps?
# - What new specs are needed?
```

**Deliverable**: Updated YAML spec file + change summary → **GET USER APPROVAL**

#### 2. Test Creation
- Create test class per new/modified spec
- Name class after acceptance criterion (e.g., `CurrentStateTrackingTest`)
- Run tests - they should fail (red phase)

#### 3. Implementation (Iterative)
```bash
# Loop until done:
1. Make small code change
2. ddev exec vendor/bin/phpunit [affected test]
3. If fail: fix CODE (not test)
4. If pass: continue
```

#### 4. Final Validation
```bash
ddev atlas                    # Map all specs (full regression)
ddev exec composer quality    # Code quality checks
```

[comment]:# (Decision Matrix: Table format for quick lookup. Each row = complete decision path.)
[comment]:# (If adding scenarios: common scenarios only, not edge cases. Keep to 6-8 rows max.)

### Decision Matrix

| Scenario | Action Steps | Key Principle |
|----------|--------------|---------------|
| **New feature** | Check if spec exists → Add/create specs → Approve → Test → Implement | No code without specs |
| **Bug report** | Find/add missing spec → Test (red) → Fix → Test (green) | Every bug = new/improved spec |
| **Refactoring** | Run all specs (baseline) → Refactor → Specs must still pass | Specs = behavior contract |
| **API change** | Find ALL affected specs → Update specs → Approve → Implement | Breaking change needs approval |
| **Spec conflict** | Explain conflict → Propose alternatives → Get approval | Never silently violate specs |

[comment]:# (Spec Format: Show minimal working example. For full details, agents should read actual spec files.)
[comment]:# (Keep examples generic - don't show specific component examples that might become outdated)

### Spec Format & Conventions

**YAML Structure:**
```yaml
name: component_name
group: category
features:
  - name: feature_name
    specs:
      - acceptanceCriteria: Clear, testable behavior statement
        test: vendor/bin/phpunit path/to/Test.php
```

**Test Class Template:**
```php
/**
 * Acceptance Criterion: [Exact text from YAML]
 */
#[Group('component')]
#[Group('feature')]
class DescriptiveTest extends TestCase
{
    public function testBehavior(): void
    {
        // Arrange → Act → Assert
    }
}
```

**Directory Structure:**
```
specs/[group]/[component].yaml → tests/PHPUnit/Unit/[Group]/[Component]/[SpecificBehavior]Test.php
```

### Spec Criticality Levels

Every spec includes a `criticality` field classifying its importance:

| Level | Breaking Impact | Use For | Examples |
|-------|----------------|---------|----------|
| **contract** | HIGH - Major version | Public API, behavioral guarantees users depend on | `Region.trigger()`, `Guard signature`, `ChainMail.get()` |
| **constraint** | MEDIUM - May cause bugs | Critical internal behavior, safety mechanisms | Execution order, validation, immutability |
| **detail** | LOW - Can change freely | Implementation choices, optimizations | Lazy loading, default conventions, strict equality |

**Criticality in YAML:**
```yaml
specs:
  - acceptanceCriteria: A region tracks its current state
    criticality: contract  # Public API - users depend on this
    intent: Provides runtime visibility into state
    test: vendor/bin/phpunit tests/.../CurrentStateTrackingTest.php
    
  - acceptanceCriteria: Transition chain is not invoked when state unchanged
    criticality: constraint  # Internal correctness requirement
    intent: Optimizes performance and prevents unnecessary processing
    test: vendor/bin/phpunit tests/.../NoTransitionOnSameStateTest.php
    
  - acceptanceCriteria: Built region uses first state as initial when none marked
    criticality: detail  # Default convention - could change
    intent: Provides sensible default behavior
    test: vendor/bin/phpunit tests/.../DefaultInitialStateTest.php
```

**Usage Guidelines:**
- **contract**: Changes require user approval + major version bump. Maximize test coverage.
- **constraint**: Changes need careful review. Breaking = subtle bugs or security issues.
- **detail**: Can evolve freely as long as contract holds. Focus on implementation quality.

**Current Distribution** (97 total specs):
- contract: 60 (62%) - Most specs define public API
- constraint: 25 (26%) - Internal correctness & safety
- detail: 12 (12%) - Performance & defaults

---

[comment]:# (Project Architecture: High-level overview with inline patterns. Not exhaustive - just enough to navigate.)
[comment]:# (Keep Core Components table current - add new components if they become core to >30% of work)

## Project Architecture

### Core Components & Patterns

| Component | Purpose | Key Pattern |
|-----------|---------|-------------|
| `Region.php` | State machine runtime | Triggers transition via guards |
| `RegionBuilder.php` | Fluent API for construction | Chainable methods, builds Region |
| `Feature/` | Modular features | Invoke ChainMail to register services |
| `Chains/` | Processing pipelines | Chain of responsibility pattern |
| `Middleware/ChainMail` | DI container | Type-based service registration |

### Key Concepts

- **Region**: Set of states (can be hierarchical or parallel)
- **Guard**: Predicate enabling transitions: `fn(object $trigger): bool`
- **Action**: Event handler executing business logic
- **Extended State**: Context data scoped to states/regions
- **Middleware**: Builder modifications via ChainMail

[comment]:# (Code Conventions: Show pattern with inline example. Every convention needs a code sample.)
[comment]:# (Update when PHP version changes or new patterns become standard)

### Code Conventions

**PHP Standards:**
```php
declare(strict_types=1);  // Always required
namespace Noem\State\...;  // PSR-4 autoloading

// Use PHP 8.4 features
public function process(User|Admin $actor): Result { ... }
```

**State Machine Pattern:**
```php
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Feature\Transitions\TransitionsFeature;

$region = (new RegionBuilder())
    ->enableFeatures(new TransitionsFeature())
    ->setStates('idle', 'processing', 'done')
    ->markInitial('idle')
    ->markFinal('done')
    ->addBuildStep(new AddTransition('idle', 'processing', fn(object $t): bool => $t->ready))
    ->onEnter('processing', fn(object $t) => $this->startWork($t))
    ->build();
```

**Feature System:**
```php
// Features modify ChainMail during registration
class MyFeature implements Feature {
    public function __invoke(ChainMail $chainMail): void {
        $chainMail->supply(fn(): MyService => new MyService());
        $chainMail->use(fn(RegionBuilder $b, callable $next) => $next($b));
    }
}
```

[comment]:# (Critical Implementation Details: These are the "gotchas" that cause repeated issues.)
[comment]:# (Format: Concept → ✅ Correct example → ❌ Wrong example with error message)
[comment]:# (Add new details here when agents make the same mistake 3+ times)
[comment]:# (Each detail should also appear in "Common Errors" table in Layer 1)

### Critical Implementation Details

**ChainMail Service Registration:**
```php
// ✅ Correct: Return type = service key
$chainMail->supply(fn(): MyService => new MyService());
$service = $chainMail->get(MyService::class);

// ❌ Wrong: Generic return type
$chainMail->supply(fn(): object => new MyService()); // Won't find service

// RegionBuilder behavior:
new RegionBuilder()           // Creates & configures default ChainMail
new RegionBuilder($chainMail) // Uses provided ChainMail as-is
```

**MetaType Usage:**
```php
// ✅ Correct: Use concrete implementations
use Noem\State\Feature\ExtendedState\ContextMetaType;
$builder->setMetaData($data, ContextMetaType::get());

// ❌ Wrong: These don't exist
MetaType::Region  // No such constant
MetaType::State   // No such constant
```

**Guard Signatures:**
```php
// ✅ Correct: Must accept trigger
fn(object $trigger): bool => $trigger->isValid

// ❌ Wrong: Missing required parameter
fn(): bool => true  // Error: "Required Parameter 0 not declared"
```

---

[comment]:# (=== LAYER 3: REFERENCE - Quick lookup information ===)
[comment]:# (Purpose: Quick scanning for specific details during implementation)
[comment]:# (Format: Heavily favor tables, code blocks, and lists over prose)
[comment]:# (Maintenance: Keep directory structure current. Update dependencies when composer.json changes.)

## Reference

### Directory Structure

[comment]:# (Directory structure: Update when major directories added/removed. Don't list every subdirectory.)
[comment]:# (Focus on directories agents interact with most frequently)

```
src/
├── Region.php, RegionBuilder.php          # Core
├── Feature/                                # Modular features
│   ├── Ai/, Async/, ExtendedState/
│   ├── Loader/, OrthogonalRegions/
│   └── Transitions/, Template/
├── Chains/                                 # Processing chains
└── Middleware/                             # ChainMail, Chain, Mesh

machines/                                   # Examples
├── frodos-journey/                         # AI + distance tracking
├── webserver/                              # HTTP server FSM
└── middleware-test-runner/                 # Spec test runner

specs/                                      # Acceptance criteria
├── core/                                   # Core components (Region, RegionBuilder)
├── chain/                                  # Middleware system
└── features/                               # Feature specs
    └── transitions.yaml                    # TransitionsFeature spec

tests/PHPUnit/
├── Unit/
│   ├── Core/[Component]/[Behavior]Test.php    # Core unit tests
│   ├── Feature/[Feature]/[Behavior]Test.php   # Feature unit tests
│   └── Middleware/[Component]/[Behavior]Test.php
└── Integration/                               # Integration scenarios
    ├── Core/Region/                           # Core integration tests
    └── Feature/[Feature]/                     # Feature integration tests
```

### TransitionsFeature Spec Organization

The TransitionsFeature spec (`specs/features/transitions.yaml`) is organized into 10 feature groups:

| Group | Purpose | Test Location |
|-------|---------|---------------|
| `feature-registration` | Service registration in ChainMail | `tests/PHPUnit/Unit/Feature/Transitions/` |
| `transition-registry` | Transition storage & retrieval | `tests/PHPUnit/Unit/Feature/Transitions/Registry/` |
| `add-transition-buildstep` | Developer API for adding transitions | `tests/PHPUnit/Unit/Feature/Transitions/AddTransition/` |
| `guard-validation` | Guard predicate validation | `tests/PHPUnit/Unit/Feature/Transitions/Guard/` |
| `transition-evaluation` | Automatic transition checking logic | `tests/PHPUnit/Unit/Feature/Transitions/Evaluation/` |
| `transition-execution` | DoTransition chain & lifecycle | `tests/PHPUnit/Unit/Feature/Transitions/DoTransition/` |
| `guard-context` | Guard parameter context | `tests/PHPUnit/Unit/Feature/Transitions/GuardContext/` |
| `integration` | Full feature integration tests | `tests/PHPUnit/Integration/Feature/Transitions/` |
| `error-handling` | Error handling & validation | `tests/PHPUnit/Unit/Feature/Transitions/ErrorHandling/` |
| `edge-cases` | Edge cases & boundary conditions | `tests/PHPUnit/Unit/Feature/Transitions/EdgeCases/` |

**Key TransitionsFeature Behaviors:**
- Enabled by default in RegionBuilder (core functionality)
- Checks transitions after each action dispatch
- Skips automatic transitions when state changes imperatively
- Prevents transitions from final states
- Waits for connected regions to finish (hierarchical states)
- First-match-wins guard evaluation
- Guards must accept trigger parameter and return bool
- Default guard is always-true when none specified

[comment]:# (Dependencies table: Keep current with composer.json. Only list runtime dependencies, not dev-only.)

### Dependencies

| Package | Purpose |
|---------|---------|
| `symfony/yaml` | YAML parsing |
| `nette/schema` | Config validation |
| `psr/container` | DI interface |
| `phpunit/phpunit` | Testing |

[comment]:# (Checklists: Keep focused on before-completion validation. Don't duplicate workflow steps.)

### Spec File Maintenance Checklist

Before closing any task:
- [ ] All acceptance criteria current and accurate
- [ ] Every spec has a `criticality` field (contract/constraint/detail)
- [ ] No duplicate or overlapping specs
- [ ] Every spec has corresponding test class
- [ ] Every test class has corresponding spec
- [ ] Test paths in YAML are correct
- [ ] All tests pass

[comment]:# (Test Runner Flags: Don't repeat basic usage from Layer 1. Show advanced options only.)

### Test Runner Flags

```bash
ddev exec machines/middleware-test-runner/run.sh --spec=specs/core/region.yaml \
  [--quiet]              # Show only errors
  [--verbose]            # Show full output
  [--stop-on-failure]    # Stop on first fail
  [--group=feature]      # Run specific group
```

[comment]:# (Composer Scripts: Keep in sync with composer.json scripts section)

### Composer Scripts

```bash
ddev exec composer test              # Run all tests
ddev exec composer test:watch        # Watch mode
ddev exec composer cs:check          # Check code style
ddev exec composer cs:fix            # Fix code style
ddev exec composer psalm             # Static analysis
ddev exec composer quality           # All quality checks
ddev exec composer quality:fix       # Fix + quality
```

### DDEV Custom Commands

```bash
ddev atlas                           # Map all spec regions (full regression)
ddev atlas --quiet                   # Quiet mode (errors only)
ddev atlas --verbose                 # Verbose output
ddev atlas --stop-on-failure         # Stop at first failure
```

**Atlas**: Automatically discovers all `.yaml` files in `specs/` directory (recursive). New spec files are automatically included - no configuration needed.

[comment]:# (State Machine Examples: Show common patterns. Keep examples simple and generic.)
[comment]:# (Don't show complex real-world examples - point to machines/ directory instead)

### State Machine Examples

**Simple Flow:**
```php
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Feature\Transitions\TransitionsFeature;

$builder = new RegionBuilder();
$builder->enableFeatures(new TransitionsFeature())
        ->setStates('start', 'middle', 'end')
        ->addBuildStep(new AddTransition('start', 'middle'))
        ->addBuildStep(new AddTransition('middle', 'end'));
$region = $builder->build();
```

**With Features:**
```php
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Template\TemplateFeature;

$region = (new RegionBuilder())
    ->enableFeatures(new ExtendedState(), new TemplateFeature())
    ->setStates('idle', 'working')
    ->build();
```

**Loading from YAML:**
```php
use Noem\State\Feature\Loader\RegionLoader;

$loader = new RegionLoader();
$builder = $loader->fromYaml($yamlContent);
$region = $builder->build();
```

---

[comment]:# (Session Start/Stuck sections: Action-oriented guidance for specific situations)
[comment]:# (Keep these brief - detailed process is in Layer 2)

## When Starting a New Session

1. Read this guide (you just did! ✅)
2. Review relevant spec files: `cat specs/[group]/[component].yaml`
3. Check git log for recent spec changes
4. Run baseline: `ddev atlas` or `ddev exec composer test`
5. Ask user for context before making changes

## When Stuck

1. **Ask user for clarification** - don't guess
2. Review existing specs for similar patterns
3. Check `machines/` for usage examples
4. Review related test classes for implementation patterns
5. When in doubt: **more granular specs > fewer broad specs**
