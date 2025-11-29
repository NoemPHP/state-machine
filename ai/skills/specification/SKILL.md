# Specification Skill - Spec-Driven Development

## Purpose

This skill covers the **spec-driven development workflow** for the Regions project. Specifications are executable contracts that define *what* the code must do, while implementation defines *how* it does it.

## Core Philosophy

**SPECS ARE THE CONTRACT** - When tests fail, fix the code, never the specs (unless user approves spec changes).

### The Golden Rules

1. **NEVER write code without specs first**
2. **NEVER modify specs without user approval**
3. **Specs describe WHAT/WHY (behavior, intent), not HOW (implementation)**
4. **ONE SPEC = ONE TEST CLASS** - maintain 1:1 mapping
5. **Every bug = new spec** - No bug fix without adding the missing specification

## The 4-Stage Workflow

### Stage 1: Feature Planning (MANDATORY before coding)

**Before writing any code:**

1. Review existing specs:
   ```bash
   cat specs/[group]/[component].yaml
   ```

2. Plan changes:
   - Which specs are affected by this change?
   - Are there conflicts with existing specs?
   - What new specs are needed?

3. **Deliverable**: Updated YAML + change summary

4. **GET USER APPROVAL** before proceeding

**Never skip this stage.** Even for "simple" changes, spec planning prevents architectural drift.

### Stage 2: Test Creation (Red Phase)

1. Create test class per spec (one-to-one mapping)
2. Implement tests that verify the spec's acceptance criteria
3. Tests should fail initially (red phase)
4. Use PHPUnit attributes for organization:
   ```php
   #[Group('component'), Group('feature')]
   ```

**Test Template:**
```php
/**
 * Acceptance Criterion: [Exact text from YAML]
 */
#[Group('component'), Group('feature')]
class DescriptiveTest extends TestCase
{
    public function testBehavior(): void
    {
        // Arrange → Act → Assert
    }
}
```

### Stage 3: Implementation (Iterative Green Phase)

**The iteration loop:**

1. Make small code change
2. Run specific test:
   ```bash
   ddev exec vendor/bin/phpunit tests/PHPUnit/Unit/[Component]/[Test].php
   ```
3. If test fails: **Fix the code** (not the test, not the spec)
4. Repeat until test passes

**Key principle**: Small iterations with immediate feedback.

### Stage 4: Final Validation

1. Run spec suite:
   ```bash
   ddev exec machines/middleware-test-runner/run.sh --spec=specs/[group]/[component].yaml
   ```

2. Run full regression:
   ```bash
   ddev atlas
   ```

3. Run quality checks:
   ```bash
   ddev exec composer quality
   ```

All must pass before considering the work complete.

## Decision Matrix

| Scenario | Action | Principle |
|----------|--------|-----------|
| **New feature** | Check specs → Add specs → Approve → Test → Implement | No code without specs |
| **Bug report** | Add missing spec → Test (red) → Fix → Test (green) | Every bug = new spec |
| **Refactoring** | Run specs (baseline) → Refactor → Specs pass | Specs = behavior contract |
| **API change** | Find ALL affected specs → Update → Approve → Implement | Breaking = approval |

## Spec Writing Guidelines

### Intent vs Implementation

**Core Principle**: Describe WHAT (behavior, API, intent) not HOW (internals, data structures).

#### The Intent Field is Critical

Every spec must answer: **"Why does this behavior matter to users or the system?"**

```yaml
# ✅ GOOD: Describes observable behavior and business value
acceptanceCriteria: A region can check if it is in a specific state
criticality: contract
intent: Enables conditional logic, allowing consumers to guard operations and validate preconditions
test: vendor/bin/phpunit tests/.../StateCheckTest.php

# ❌ BAD: Describes internal implementation
acceptanceCriteria: Region stores current state in a private property
criticality: constraint  # Wrong! This isn't even constraint-worthy
intent: Uses encapsulation for state storage
test: vendor/bin/phpunit tests/.../StateStorageTest.php  # Tests internals, not behavior
```

#### Red Flags: Spec is Too Implementation-Focused

Ask these questions about every spec:

- **Could this be implemented differently?** → If yes, it's likely a detail
- **Does this test private methods/data structures?** → Implementation detail
- **Uses words like "stores", "uses", "maintains", "organized by"?** → Red flag
- **Would a user notice if this changed?** → If no, consider removing or marking detail

#### Criticality and Abstraction Levels

| Criticality | Focus | Abstraction | Examples |
|-------------|-------|-------------|----------|
| **contract** | Observable behavior, public API | HIGH | "Can check if in state", "Fires lifecycle events" |
| **constraint** | Internal correctness, safety | MEDIUM | "Skips transitions when unchanged", "Validates config" |
| **detail** | Performance, defaults | LOW | "Uses lazy loading", "Defaults to first state" |

**Usage Guidelines:**
- **contract**: User approval + major version bump required
- **constraint**: Careful review required - breaking causes subtle bugs
- **detail**: Can evolve freely - focus on implementation quality

**Current Distribution** (97 total specs):
- contract: 78% - Public API & lifecycle guarantees
- constraint: 21% - Internal correctness & safety
- detail: 1% - Performance & defaults

#### Consolidation Patterns

Watch for over-specification:
- Multiple specs testing slight variations
- Testing each step of a process separately
- Specs like "Extracts X", "Applies X", "Registers X" for same feature

**Before** (7 specs - over-specified):
- ProcessArray extracts states
- ProcessArray extracts transitions
- ProcessArray applies states to builder
- ProcessArray applies transitions to builder
- (etc.)

**After** (2 specs - consolidated):
- ProcessArray builds valid regions from configuration
- ProcessArray validates configuration before building

#### When in Doubt

1. **Read the intent** - Can't write compelling intent? Question the spec
2. **Check criticality** - Detail specs are candidates for removal
3. **Look for duplication** - Integration tests often cover granular specs
4. **Ask the user** - Propose consolidation during planning

## Spec Format & Conventions

### Component Specs

Features, middleware, and core components:

```yaml
name: component_name
group: category
features:
  - name: feature_name
    specs:
      - acceptanceCriteria: Clear, testable behavior
        criticality: contract|constraint|detail
        intent: Why this matters to users/system
        test: vendor/bin/phpunit path/to/Test.php
```

### Machine Specs

Complete applications (end-to-end):

```yaml
name: machine_name
group: machines
description: High-level description of machine purpose
features:
  - name: feature_group_name
    description: What this group of behaviors accomplishes
    specs:
      - acceptanceCriteria: Observable end-to-end behavior
        criticality: contract|constraint
        intent: Why this behavior matters for the application
        test: vendor/bin/phpunit tests/PHPUnit/E2E/MachineName/Basic/TestName.php
```

**Key Differences:**
- **Scope**: Machines test complete applications; components test isolated features
- **Location**: E2E tests in `tests/PHPUnit/E2E/`, specs in `specs/machines/`
- **Criticality**: Machines rarely use `detail` - focus on contract behaviors
- **Mocking**: Use mock infrastructure for external dependencies (sockets, files, APIs)

## Spec Maintenance Checklist

Before closing any task involving specs:

- [ ] All specs have `criticality` field
- [ ] All specs have compelling `intent`
- [ ] No duplicate or overlapping specs
- [ ] Every spec has corresponding test
- [ ] Test paths in YAML are correct
- [ ] All tests pass
- [ ] User has approved all spec changes

## Common Pitfalls

### Pitfall 1: "Fixing" Specs to Match Code

**Wrong approach:**
```
Test fails → "The spec is wrong" → Modify spec
```

**Correct approach:**
```
Test fails → "The code doesn't match the contract" → Fix code
```

### Pitfall 2: Skipping Planning Phase

**Wrong approach:**
```
"This is a quick fix" → Write code directly → Tests fail unexpectedly
```

**Correct approach:**
```
Review specs → Identify gaps → Get approval → Write tests → Implement
```

### Pitfall 3: Testing Implementation Details

**Wrong approach:**
```yaml
acceptanceCriteria: StateManager stores states in associative array
criticality: constraint
```

**Correct approach:**
```yaml
acceptanceCriteria: StateManager provides O(1) state lookup by name
criticality: constraint
intent: Enables efficient state queries in machines with many states
```

## Integration with Other Skills

- **Before coding** → Use this skill to plan and validate specs
- **During testing** → Use testing skill with specs as reference
- **For documentation** → Use documentation skill to explain specs in README
- **For core development** → Core-development skill implements based on specs
- **For region development** → Region-development skill follows spec contracts

## Examples from Project History

### Example 1: Successful Spec-Driven Bug Fix

**Bug Report**: "Child regions don't update state when parent triggers action"

**Spec-Driven Response**:
1. Added spec to `specs/core/region.yaml`:
   ```yaml
   acceptanceCriteria: Connected regions update state when receiving triggers from parent
   criticality: contract
   intent: Ensures hierarchical state machines maintain consistency across parent-child relationships
   ```
2. Created failing test: `ConnectedRegionsStateUpdateTest.php`
3. Fixed `Region::processOneAction()` to properly handle child dispatch
4. Test passes, regression suite passes
5. Documented fix in `BUG_CONNECTED_REGIONS_STATE_UPDATE.md`

### Example 2: Spec Consolidation

**Before**: 7 specs for ProcessArray feature
- Each tested a granular step (extract, apply, register)
- Heavy duplication in tests
- Intent unclear for individual steps

**After**: 2 consolidated specs
- "ProcessArray builds valid regions from configuration"
- "ProcessArray validates configuration before building"
- Clear intent, better coverage, less maintenance

**Result**: Better test organization, clearer contracts

## Reference Commands

```bash
# View all specs for a component
cat specs/[group]/[component].yaml

# Run specific spec suite
ddev exec machines/middleware-test-runner/run.sh --spec=specs/[group]/[component].yaml

# Run all specs (full regression)
ddev atlas

# Run with flags
ddev atlas --quiet              # Errors only
ddev atlas --verbose            # Full output
ddev atlas --stop-on-failure    # Stop at first failure
```

## When to Load This Skill

**Always load when:**
- Planning new features or changes
- Responding to bug reports
- Reviewing or modifying specs
- Starting any development work
- User mentions specs, contracts, or acceptance criteria

**Combine with:**
- **testing** skill - for test implementation details
- **core-development** skill - for implementation guidance
- **documentation** skill - for documenting spec changes
