# Noem State Machine

Noem State Machine is a sophisticated PHP library implementing event-based finite state machines with support for hierarchical and parallel states, middleware, and extensive feature system.

## Project Architecture

### Core Components
- **`src/Region.php`** - Main state machine runtime engine
- **`src/RegionBuilder.php`** - Fluent API for building state machines
- **`src/Feature/`** - Modular feature system (AI, Async, Templates, etc.)
- **`src/Chains/`** - Chain of responsibility pattern for extensible processing
- **`machines/`** - Example state machine implementations

### Key Concepts
- **Regions**: Horizontal sets of states that can be hierarchical or parallel
- **Guards**: Predicates that enable/disable transitions
- **Actions**: Event handlers that execute business logic
- **Extended State**: Context data scoped to states or regions
- **Middleware**: Reusable modifications applied during machine construction

## Development Environment

### Prerequisites
- PHP 8.4+
- DDEV (development is done using DDEV containers)
- Composer

### Setup Commands
```bash
# All commands must be prefixed with 'ddev exec' to run inside the container
ddev exec composer install
ddev exec composer test
ddev exec composer cs:check
ddev exec composer psalm
```

### Running Examples
```bash
# Execute state machine examples
ddev exec php machines/frodos-journey/machine.php
ddev exec php machines/webserver/machine.php

# Run test runner for acceptance criteria
ddev exec machines/middleware-test-runner/run.sh --spec=specs/core/region.yaml --quiet  # Show only errors
```

## Testing & Quality

### Spec-Driven Development Approach

The project follows a **specification-driven development** methodology designed for **AI-assisted code maintenance**. Specs serve as **guardrails** that ensure consistent code quality and architecture across multiple AI agent sessions.

#### Philosophy: Test-Driven Development for the AI Era

**The Problem**: Large codebases maintained by AI agents can drift in quality and architecture without proper guardrails. Traditional TDD assumes human developers write tests, but AI agents can assist with both specification and implementation.

**The Solution: Specs as Source of Truth**
- **Specs before code**: All feature work begins with spec planning
- **Agents test iteratively**: Agents must validate against specs during implementation
- **One spec = One test class**: Each acceptance criterion gets its own dedicated test class
- **Living documentation**: YAML specs serve as executable contract between agent sessions
- **Guaranteed consistency**: Code is always up to spec, architecture remains coherent

#### AI Agent Workflow

**🚨 CRITICAL**: AI agents MUST NOT write implementation code without following this workflow:

##### 1. Feature Planning Stage (MANDATORY)
Before making ANY code changes, agents must:

```bash
# Review existing specs for the component
cat specs/[group]/[component].yaml

# Identify related specs that might be affected
grep -r "keyword" specs/

# Check existing tests
ddev exec vendor/bin/phpunit tests/PHPUnit/[Unit|Integration]/[Component]
```

**Planning Tasks**:
- ✅ Review existing acceptance criteria for the component
- ✅ Identify specs that will be modified or removed
- ✅ Detect conflicts and overlaps with existing specs
- ✅ Design new acceptance criteria based on user request
- ✅ Update YAML spec file(s) with changes
- ✅ Get user approval before proceeding to implementation

**Output**: Updated YAML spec file(s) with clear change summary

##### 2. Test Creation Stage
After spec approval:
- Create/modify test classes for new/changed acceptance criteria
- Follow one-spec-one-test-class pattern
- Ensure test names match acceptance criteria exactly
- Run tests to verify they fail appropriately (red phase)

##### 3. Implementation Stage with Iterative Validation
**🚨 CRITICAL**: Agents must test against specs continuously during implementation:

```bash
# After each significant code change, validate:
ddev exec vendor/bin/phpunit tests/PHPUnit/Unit/[Component]/[SpecificTest.php]

# Check for regressions in related specs:
ddev exec vendor/bin/phpunit --group [component]

# Full spec validation:
ddev exec machines/middleware-test-runner/run.sh --spec=specs/[group]/[component].yaml
```

**Implementation Loop**:
1. Make focused code change
2. Run affected spec tests
3. If tests fail: **fix code** (not tests - specs are the contract!)
4. If tests pass: proceed to next change
5. Repeat until all specs pass

##### 4. Final Validation
Before marking work complete:
```bash
# Run all component tests
ddev exec vendor/bin/phpunit tests/PHPUnit/[Unit|Integration]/[Component]

# Run full quality checks
ddev exec composer quality

# Validate spec file integrity
ddev exec machines/middleware-test-runner/run.sh --spec=specs/[group]/[component].yaml
```

#### Benefits for AI Agent Maintenance
- **Session consistency**: New agent sessions can resume work by reading specs
- **Architecture preservation**: Specs encode design decisions that agents must respect
- **Quality guarantee**: Code cannot drift from spec without failing tests
- **Conflict prevention**: Spec planning phase catches overlaps before implementation
- **Refactoring safety**: Specs define behavior contract, internals can be refactored freely
- **Multi-agent collaboration**: Specs provide common understanding across agents
- **Traceability**: Direct mapping between specs and implementation
- **Documentation**: Specs double as technical documentation

#### Directory Structure
```
specs/
├── core/
│   └── region.yaml          # Specs for core Region runtime
├── chain/
│   └── middleware.yaml      # Specs for middleware system
└── [feature]/
    └── [component].yaml

tests/PHPUnit/
├── Unit/
│   ├── Core/
│   │   └── Region/          # One test class per spec
│   │       ├── CurrentStateTrackingTest.php
│   │       ├── StateCheckTest.php
│   │       └── ...
│   └── Middleware/
│       ├── Chain/
│       ├── ChainMail/
│       └── Mesh/
└── Integration/
    └── Core/
        └── Region/          # Integration scenarios
```

#### YAML Spec Format
```yaml
name: component_name
group: category
description: High-level description of the component

features:
  - name: feature_name
    description: What this feature does
    specs:
      - acceptanceCriteria: Clear, testable statement of expected behavior
        test: vendor/bin/phpunit path/to/SpecificTest.php
```

#### Test Class Conventions
```php
/**
 * Acceptance Criterion: [Exact text from YAML spec]
 */
#[Group('component')]
#[Group('feature')]
class DescriptiveAcceptanceCriterionTest extends TestCase
{
    public function testSpecificBehavior(): void
    {
        // Arrange - Set up the test scenario
        // Act - Execute the behavior
        // Assert - Verify the acceptance criterion
    }
}
```

#### Spec Conflict Detection and Resolution

When planning feature changes, agents must identify and resolve spec conflicts:

**Types of Conflicts**:
1. **Duplicate acceptance criteria**: Two specs testing the same behavior
2. **Contradictory specs**: Specs requiring mutually exclusive behaviors
3. **Overlapping concerns**: Specs testing related but distinct behaviors that could interfere
4. **Missing dependencies**: New spec requires behavior not yet specified
5. **Obsolete specs**: Existing specs that become invalid with new changes


**Resolution Strategies**:
- **Merge**: Combine overlapping specs into one comprehensive spec
- **Refine**: Make specs more specific to eliminate overlap
- **Deprecate**: Remove obsolete specs and their tests
- **Split**: Break overly broad specs into focused ones
- **Reorder**: Change spec organization to clarify relationships

**Example Planning Output**:
```markdown
## Feature Planning: Add async event processing

### Affected Specs
- specs/core/region.yaml
  - Modified: "Events triggered during dispatch are queued for next cycle"
    - Reason: Now supports both sync and async queuing
    - Changes: Add async parameter to acceptance criteria
  - Added: "Async events can be processed in separate execution context"
    - New test: AsyncEventProcessingTest.php
  - Conflict Detected: Overlaps with "Dispatching processes all queued events in order"
    - Resolution: Refine async spec to clarify it maintains order within async context

### New Tests Required
- tests/PHPUnit/Unit/Core/Region/AsyncEventProcessingTest.php
- tests/PHPUnit/Integration/Core/Region/AsyncEventOrderingTest.php

### Tests to Modify
- tests/PHPUnit/Unit/Core/Region/NestedEventDispatchTest.php
  - Add async scenario test case

### Tests to Remove
- None

### Approval Requested
Please review conflict resolution for event ordering before proceeding.
```

#### Benefits
- **Traceability**: Direct mapping between specs and implementation
- **Maintainability**: Easy to locate and update specific behavior tests
- **Clarity**: New team members can understand what's tested at a glance
- **Documentation**: Specs double as technical documentation
- **Refactoring confidence**: Granular tests make it safe to refactor internals

#### Running Specs
```bash
# Run all tests for a component
ddev exec phpunit tests/PHPUnit/Unit/Core/Region

# Run specific feature group
ddev exec phpunit --group state-management

# Run acceptance test suite (specific spec)
ddev exec machines/middleware-test-runner/run.sh --spec=specs/core/region.yaml

# Run specific spec file
ddev exec machines/middleware-test-runner/run.sh --spec=specs/core/region.yaml
```

#### Creating New Specs: Best Practices

When creating specs for a new component or feature:

**1. Analyze the Component**
```bash
# Review the source code
cat src/[Component].php

# Check existing usage patterns
grep -r "new [Component]" src/ machines/

# Review existing tests (if any)
ls tests/PHPUnit/**/[Component]*
```

**2. Extract Behaviors**
- Read the source code to understand public API
- Identify distinct, testable behaviors
- Look for edge cases and error conditions
- Consider lifecycle and state management
- Note dependencies and chain interactions

**3. Group Logically**
Organize specs into coherent feature groups:
- **Core behavior**: Basic functionality (state management, CRUD operations)
- **Integration**: How component interacts with others
- **Error handling**: Edge cases, validation, exceptions
- **Performance**: Caching, lazy loading, optimization
- **Lifecycle**: Initialization, cleanup, callbacks

**4. Write Atomic Acceptance Criteria**
Each spec should be:
- ✅ **Atomic**: Tests one specific behavior
- ✅ **Clear**: Unambiguous statement of expected behavior
- ✅ **Testable**: Can be verified with code
- ✅ **Independent**: Not dependent on other specs
- ✅ **Complete**: Fully describes the behavior

**Good Examples**:
```yaml
- acceptanceCriteria: A region tracks its current state
- acceptanceCriteria: Action chain receives region and trigger payload
- acceptanceCriteria: Transition chain is not invoked when state remains unchanged
```

**Bad Examples**:
```yaml
- acceptanceCriteria: Region works correctly  # Too vague
- acceptanceCriteria: Events are processed and transitions happen  # Not atomic
- acceptanceCriteria: Should handle edge cases  # Not specific
```

**5. Name Test Classes Descriptively**
Test class names should directly reflect acceptance criteria:
- "A region tracks its current state" → `CurrentStateTrackingTest`
- "Action chain receives region and trigger payload" → `ActionChainContextTest`
- "Memoization uses strict equality by default" → `MemoizationEqualityTest`

**6. Maintain Spec Hygiene**
As codebase evolves:
- Remove specs for deprecated functionality immediately
- Update specs when behavior changes (don't just update tests!)
- Consolidate duplicate or overlapping specs
- Keep spec file organized and well-commented
- Ensure every spec has a corresponding test class

#### Spec Maintenance Workflow

When modifying existing code:

```bash
# 1. Identify affected specs
grep -r "[method/class name]" specs/

# 2. Review current spec file
cat specs/[group]/[component].yaml

# 3. Plan spec changes
# - Which specs need updates?
# - Which specs are now obsolete?
# - What new specs are needed?

# 4. Update spec file BEFORE changing code
vim specs/[group]/[component].yaml

# 5. Update/create test classes to match specs
# 6. Run tests (they should fail if behavior changing)
# 7. Update implementation
# 8. Iterate until all specs pass
```

**Spec File Maintenance Checklist**:
- [ ] All acceptance criteria are current and accurate
- [ ] No duplicate or overlapping specs
- [ ] All specs have corresponding test classes
- [ ] All test classes have corresponding specs
- [ ] Spec groups are logically organized
- [ ] Test paths in YAML are correct and up-to-date
- [ ] Legacy test command (if present) still works

### Test Execution
```bash
# Run all tests
ddev exec composer test
ddev exec phpunit

# Watch tests (auto-rerun on changes)
ddev exec composer test:watch

# Run specific test
ddev exec phpunit --filter "TestClassName"

# Run acceptance criteria test suite (--spec required)
ddev exec machines/middleware-test-runner/run.sh --spec=specs/[group]/[component].yaml
```

### Agent Decision Tree: When to Create/Update Specs

**Scenario: User requests new feature**
1. ✅ Check if component spec exists
2. ✅ If yes: Add acceptance criteria to existing spec file
3. ✅ If no: Create new spec file with all acceptance criteria
4. ✅ Create corresponding test classes
5. ✅ Implement feature iteratively with test validation
6. ✅ Never implement without specs!

**Scenario: User reports bug**
1. ✅ Identify which spec should have caught the bug
2. ✅ If spec exists but test is inadequate: Strengthen test
3. ✅ If spec missing: Add acceptance criterion for the fix
4. ✅ Update test to catch the bug (red phase)
5. ✅ Fix implementation (green phase)
6. ✅ Every bug fix must result in a new or strengthened spec

**Scenario: User requests refactoring**
1. ✅ Review specs for the component
2. ✅ Specs define the contract - they should NOT change
3. ✅ Run all specs before refactoring (establish baseline)
4. ✅ Refactor implementation
5. ✅ Run specs continuously during refactoring
6. ✅ All specs must still pass after refactoring
7. ✅ If specs fail: Fix code, not specs (specs are the contract!)

**Scenario: User requests API change**
1. ✅ This is a breaking change - plan carefully
2. ✅ Identify ALL affected specs across entire codebase
3. ✅ Update specs to reflect new API
4. ✅ Update test classes
5. ✅ Update implementation
6. ✅ Check for ripple effects in dependent components

**Scenario: Specs conflict with user request**
1. ✅ Explain the conflict to user clearly
2. ✅ Show which specs would be violated
3. ✅ Propose alternatives that maintain specs
4. ✅ If user insists: Get explicit approval to modify specs
5. ✅ Document the architectural decision
6. ✅ Never silently violate specs!

**Scenario: Found code without specs**
1. ✅ DO NOT modify the code yet!
2. ✅ Create specs by analyzing current behavior
3. ✅ Create test classes for new specs
4. ✅ Run tests to verify they pass (document current behavior)
5. ✅ Now you can safely modify the code
6. ✅ All code must have specs before modification

**Scenario: Starting new agent session**
1. ✅ Read AGENTS.md to understand workflow
2. ✅ Review all spec files in `specs/` directory
3. ✅ Understand the architecture from specs
4. ✅ Check recent spec changes in git history
5. ✅ Run full test suite to establish baseline
6. ✅ Ask user for context before making changes

### Code Quality
```bash
# Check code style
ddev exec composer cs:check

# Fix code style
ddev exec composer cs:fix

# Static analysis
ddev exec composer psalm

# Run all quality checks
ddev exec composer quality
```

### Quick Reference for AI Agents

#### 🚨 Cardinal Rules
1. **NEVER write implementation code without specs**
2. **NEVER modify specs without user approval**
3. **ALWAYS test iteratively during implementation**
4. **SPECS ARE THE CONTRACT** - fix code, not specs when tests fail
5. **ONE SPEC = ONE TEST CLASS** - maintain 1:1 mapping

#### 🔄 Typical Workflow
```
User Request → Feature Planning → Spec Updates (get approval) → 
Test Creation (red) → Implementation (iterative) → All Specs Pass (green) → Done
```

#### 📋 Pre-Implementation Checklist
- [ ] Read relevant spec file(s)
- [ ] Identify affected/related specs
- [ ] Plan spec changes (add/modify/remove)
- [ ] Get user approval for spec changes
- [ ] Create/update test classes
- [ ] Tests fail appropriately (red phase)

#### ⚙️ During Implementation
- [ ] Make small, focused changes
- [ ] Run affected spec tests after each change
- [ ] Fix code if tests fail (not specs!)
- [ ] Check for regressions in related specs
- [ ] Commit when specs pass

#### ✅ Before Completing Task
- [ ] All component specs pass
- [ ] No regressions in other specs
- [ ] Code quality checks pass
- [ ] Specs updated and documented
- [ ] Test paths in YAML are correct

#### 🔍 Common Commands
```bash
# Review specs
cat specs/[group]/[component].yaml
grep -r "keyword" specs/

# Run tests
ddev exec vendor/bin/phpunit tests/PHPUnit/Unit/[Component]/[Test].php
ddev exec vendor/bin/phpunit --group [component]
ddev exec machines/middleware-test-runner/run.sh --spec=specs/[group]/[component].yaml

# Quality
ddev exec composer quality
ddev exec composer test:watch
```

#### 💡 When in Doubt
1. Ask user for clarification
2. Review existing specs for patterns
3. Check similar components for precedent
4. Err on the side of more specs, not fewer
5. Make specs granular and focused

## Code Conventions

### PHP Standards
- **PHP 8.4+ features**: Use typed properties, union types, match expressions
- **PSR-4 autoloading**: `Noem\State\` namespace maps to `src/`
- **Strict types**: Always use `declare(strict_types=1);`
- **Return types**: Always declare return types on methods

### State Machine Patterns
```php
// Use RegionBuilder for fluent API construction
$region = (new RegionBuilder())
    ->setStates('initial', 'processing', 'final')
    ->markInitial('initial')
    ->markFinal('final')
    ->pushTransition('initial', 'processing', fn(object $trigger): bool => true)
    ->onEnter('processing', function(object $trigger) {
        // Entry callback logic
    })
    ->build();

// Guards should return boolean
->pushTransition('from', 'to', fn(object $trigger): bool => $trigger->isValid)

// Actions can return generators for async operations
->onAction('state', function(object $trigger): Generator {
    yield from $this->processAsync($trigger);
})
```

### YAML Configuration Format
```yaml
states:
  - name: state_name
    transitions:
      - target: next_state
        guard: !php return function($trigger): bool { return true; }
    onEnter:
      - run: !php return callbackFunction()
    action:
      - run: !php return actionFunction()
    regions:
      - states: [nested_states]
initial: initial_state
final: final_state
```

### Feature System
- Features extend `Noem\State\Feature\Feature`
- Implement `register(RegionBuilder $builder): void` method
- Use dependency injection through ChainMail container
- Features can require other features via `RequiresFeature` trait

## Key Directories

### `src/`
- **Core classes**: Region, RegionBuilder, Events, Connection
- **Feature/**: Modular feature implementations
  - **Ai/**: AI integration and templating
  - **Async/**: Asynchronous operation support
  - **ExtendedState/**: Context management
  - **Loader/**: YAML/configuration loading
  - **OrthogonalRegions/**: Parallel state support
- **Chains/**: Processing chain implementations
- **Middleware/**: Middleware system components

### `machines/`
- **frodos-journey/**: Complex example with AI integration and distance tracking
- **webserver/**: HTTP server state machine with connection spawning
- **coding/**: Development workflow state machine
- **directory-docs/**: Documentation generation example
- **middleware-test-runner/**: Acceptance criteria test runner

### `tests/`
- **PHPUnit/**: Unit and integration tests
- **resources/**: Test fixtures and data

### `specs/`
- **chain/**: YAML specifications for acceptance criteria
  - **middleware.yaml**: Comprehensive middleware system acceptance tests

## Common Patterns

### Creating State Machines
1. **Simple Linear Flow**:
   ```php
   $builder = new RegionBuilder();
   $builder->setStates('start', 'middle', 'end')
           ->pushTransition('start', 'middle')
           ->pushTransition('middle', 'end');
   ```

2. **With Guards and Actions**:
   ```php
   ->pushTransition('from', 'to', fn($trigger): bool => $trigger->condition)
   ->onEnter('state', fn($trigger) => $this->handleEntry($trigger))
   ```

3. **Loading from YAML**:
   ```php
   $loader = new RegionLoader();
   $builder = $loader->fromYaml($yamlContent);
   ```

### Working with Features
```php
$region = (new RegionBuilder())
    ->pushFeature(new ExtendedState())
    ->pushFeature(new TemplateFeature())
    ->pushFeature(new AiFeature())
    // ... configure states and transitions
    ->build();
```

### Middleware Usage
```php
$middleware = function(RegionBuilder $builder, \Closure $next) {
    // Modify builder before construction
    $builder->eachState(fn($state) => $builder->onEnter($state, $callback));
    return $next($builder);
};

$builder->pushMiddleware($middleware);
```

## Test Runner System

### Overview
The project includes a state machine-based test runner (`machines/middleware-test-runner/`) for executing acceptance criteria defined in YAML specifications.

**Note:** The `--spec` parameter is required. The test runner no longer has a default spec file fallback.

### Usage
```bash
# Run tests (--spec parameter is required)
ddev exec machines/middleware-test-runner/run.sh --spec=specs/core/region.yaml

# Quiet mode (errors only)
ddev exec machines/middleware-test-runner/run.sh --spec=specs/chain/middleware.yaml --quiet

# Verbose mode (with output)
ddev exec machines/middleware-test-runner/run.sh --spec=specs/core/region.yaml --verbose

# Stop on first failure
ddev exec machines/middleware-test-runner/run.sh --spec=specs/core/region.yaml --stop-on-failure

# Run specific feature group
ddev exec machines/middleware-test-runner/run.sh --spec=specs/chain/middleware.yaml --group=chain
```

### Test Specification Format
Tests are defined in YAML files under `specs/`:
```yaml
name: test_suite_name
features:
  - name: feature_name
    description: Feature description
    specs:
      - acceptanceCriteria: What should be tested
        test: command to execute test
```

## Dependencies & External Integrations

### Required Dependencies
- **symfony/yaml**: YAML configuration parsing
- **nette/schema**: Configuration validation
- **psr/container**: Dependency injection interface

### AI Features
- Uses template system with `{{#complete}}` directives for AI completion
- Integrates with various AI providers through template feature
- Supports streaming responses via PHP generators

### Testing Dependencies
- **phpunit/phpunit**: Testing framework
- **mockery/mockery**: Mocking library
- **spatie/phpunit-watcher**: Test watching

## Build & Deployment

### Composer Scripts
```bash
# Development workflow
ddev exec composer quality:fix  # Fix code style and run quality checks
ddev exec composer test:watch   # Watch tests during development

# CI/CD pipeline
ddev exec composer quality      # Run all quality checks (CI)
```

### File Structure Conventions
- One class per file following PSR-4
- Feature classes in `src/Feature/{FeatureName}/`
- Tests mirror source structure in `tests/PHPUnit/`
- Examples in `machines/{example-name}/`
- Test specifications in `specs/{category}/`

## Common Issues & Solutions

### State Machine Looping
- Ensure guards are mutually exclusive
- Check that state transitions properly update context
- Use simple state machine designs when possible
- Avoid complex nested transitions

### Test Runner Issues
- If tests repeat: Check state machine transitions
- For hanging tests: Use `--stop-on-failure` flag
- Missing test files: Verify paths relative to project root