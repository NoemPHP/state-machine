# Specification Skill - Spec-Driven Development Protocol

## ⚡ EXECUTION PROTOCOL - NON-NEGOTIABLE

**SPECS ARE THE CONTRACT** - When tests fail → fix code. When specs are wrong → ask user. NEVER modify specs without approval.

---

## 🚫 ABSOLUTE RULES - NEVER VIOLATE

| Rule | Violation = Consequence |
|------|-------------------------|
| **NEVER write code without specs first** | STOP → Create specs → Get approval → Resume |
| **NEVER modify specs without user approval** | STOP → Ask user → Get approval → Modify |
| **ONE SPEC = ONE TEST CLASS** | STOP → Consolidate or split → Maintain 1:1 mapping |
| **Every bug = new spec FIRST** | STOP → Create spec → Write test → Fix code |
| **Specs describe WHAT/WHY, not HOW** | STOP → Rewrite spec → Focus on behavior |

---

## ⚙️ MANDATORY 4-STAGE WORKFLOW

**YOU MUST EXECUTE ALL STAGES IN ORDER. NO SKIPPING.**

### STAGE 1: FEATURE PLANNING (MANDATORY BEFORE ANY CODE)

**EXECUTE THIS PROTOCOL:**

```
STEP 1: Review existing specs
  └─ RUN: cat specs/[group]/[component].yaml

STEP 2: Analyze impact
  ├─ Which specs affected?
  ├─ Conflicts with existing specs?
  └─ What new specs needed?

STEP 3: Create spec changes
  └─ UPDATE: YAML with new/modified specs

STEP 4: GET USER APPROVAL
  └─ STOP: Do not proceed without approval

STEP 5: Proceed to Stage 2
  └─ OUTPUT: "✓ Specs approved - proceeding to tests"
```

**CHECKPOINT VERIFICATION:**
- [ ] Existing specs reviewed
- [ ] Impact analysis complete
- [ ] YAML updated
- [ ] User approval obtained

**FAILURE TO COMPLETE = ABORT TASK**

### STAGE 2: TEST CREATION (RED PHASE)

**EXECUTE THIS PROTOCOL:**

```
STEP 1: Create test class (ONE per spec)
  └─ FILE: tests/PHPUnit/[Type]/[Component]/[Behavior]Test.php

STEP 2: Implement test
  ├─ Copy acceptance criteria EXACTLY to PHPDoc
  ├─ Add PHPUnit attributes: #[Group('component'), Group('feature')]
  └─ Write test using Arrange → Act → Assert

STEP 3: RUN test (MUST FAIL)
  ├─ RUN: ddev exec vendor/bin/phpunit [test-path]
  └─ VERIFY: Test fails (red phase)

STEP 4: Proceed to Stage 3
  └─ OUTPUT: "✓ Test failing - proceeding to implementation"
```

**Test must follow template:**

```php
/**
 * Acceptance Criterion: [EXACT TEXT FROM YAML]
 */
#[Group('component'), Group('feature')]
class DescriptiveTest extends TestCase
{
    public function testBehavior(): void
    {
        // Arrange
        // Act
        // Assert
    }
}
```

### STAGE 3: IMPLEMENTATION (GREEN PHASE)

**EXECUTE THIS ITERATION LOOP:**

```
LOOP:
  STEP 1: Make small code change

  STEP 2: RUN specific test
    └─ RUN: ddev exec vendor/bin/phpunit [test-path]

  STEP 3: Evaluate result
    ├─ PASS → Exit loop, proceed to Stage 4
    └─ FAIL → Fix CODE (not test, not spec), repeat loop

REPEAT until test passes
```

**CRITICAL**: Small iterations. Immediate feedback. Fix code ONLY.

### STAGE 4: FINAL VALIDATION

**EXECUTE ALL CHECKS:**

```
CHECK 1: Spec suite
  └─ RUN: ddev exec machines/middleware-test-runner/run.sh --spec=specs/[group]/[component].yaml
  └─ MUST: All pass

CHECK 2: Full regression
  └─ RUN: ddev atlas
  └─ MUST: All pass

CHECK 3: Quality checks
  └─ RUN: ddev exec composer quality
  └─ MUST: All pass

CHECK 4: Verification
  └─ All checks passed → Task complete
  └─ Any failures → Fix and re-run ALL checks
```

**ALL CHECKS MUST PASS BEFORE COMPLETION**

---

## 🎯 DECISION MATRIX - USE THIS FOR EVERY TASK

| Scenario | Mandatory Actions | Principle |
|----------|------------------|-----------|
| **New feature** | Review specs → Add specs → Approve → Test → Implement | No code without specs |
| **Bug report** | Add missing spec → Test (red) → Fix → Test (green) | Every bug = new spec |
| **Refactoring** | Run specs (baseline) → Refactor → Specs pass | Specs = behavior contract |
| **API change** | Find ALL affected specs → Update → Approve → Implement | Breaking = approval required |
| **Test fails** | Fix CODE (not test/spec) | Specs are contract |
| **Spec seems wrong** | Ask user, get approval | Never assume |

---

## ✍️ SPEC WRITING PROTOCOL

### CRITICAL PRINCIPLE: WHAT/WHY NOT HOW

**Execute intent verification:**

```
FOR each spec:
  QUESTION 1: Could this be implemented differently?
    └─ YES → Likely a detail, consider removing
    └─ NO → Continue

  QUESTION 2: Does this test private methods/data?
    └─ YES → STOP - Implementation detail, remove spec
    └─ NO → Continue

  QUESTION 3: Uses "stores", "uses", "maintains"?
    └─ YES → RED FLAG - Rewrite to focus on behavior
    └─ NO → Continue

  QUESTION 4: Would user notice if this changed?
    └─ NO → Consider marking 'detail' or removing
    └─ YES → Proceed

  QUESTION 5: Can you write compelling intent?
    └─ NO → Question the spec's value
    └─ YES → Proceed
```

### CRITICALITY CLASSIFICATION

| Level | Focus | User Impact | Breaking Change |
|-------|-------|-------------|-----------------|
| **contract** | Public API, observable behavior | HIGH | Major version |
| **constraint** | Internal correctness, safety | MEDIUM | Careful review |
| **detail** | Performance, defaults | LOW | Can evolve freely |

**Current distribution** (97 specs): 78% contract, 21% constraint, 1% detail

**GUIDELINE**: Aim for contract specs. Question constraints. Minimize details.

### SPEC CONSOLIDATION PROTOCOL

**Red flags for over-specification:**
- Multiple specs testing slight variations
- Testing each process step separately
- Granular specs like "Extract X", "Apply X", "Register X"

**Execute consolidation check:**

```
IF similar specs > 3:
  STEP 1: Identify common theme
  STEP 2: Propose consolidation to user
  STEP 3: Get approval
  STEP 4: Create consolidated spec
  STEP 5: Update test
  STEP 6: Remove old specs
```

---

## 📋 SPEC FORMAT REQUIREMENTS

### Component Specs

```yaml
name: component_name
group: category
features:
  - name: feature_name
    specs:
      - acceptanceCriteria: Clear, testable, OBSERVABLE behavior
        criticality: contract|constraint|detail
        intent: WHY this matters (business value, user impact)
        test: vendor/bin/phpunit path/to/Test.php
```

**MANDATORY FIELDS:**
- ✅ `acceptanceCriteria` - Observable behavior ONLY
- ✅ `criticality` - contract/constraint/detail
- ✅ `intent` - Business value explanation
- ✅ `test` - Correct path to test file

### Machine Specs

```yaml
name: machine_name
group: machines
description: Application purpose
features:
  - name: feature_group
    description: Group accomplishment
    specs:
      - acceptanceCriteria: End-to-end OBSERVABLE behavior
        criticality: contract|constraint
        intent: Application-level business value
        test: vendor/bin/phpunit tests/PHPUnit/E2E/MachineName/Basic/TestName.php
```

**Differences from component specs:**
- Scope: Complete applications (E2E)
- Location: `tests/PHPUnit/E2E/`, `specs/machines/`
- Rarely use `detail` criticality
- Use mock infrastructure for dependencies

---

## ✅ PRE-COMPLETION CHECKLIST

**Execute before closing ANY spec-related task:**

- [ ] All specs have `criticality` field
- [ ] All specs have compelling `intent`
- [ ] No duplicate/overlapping specs
- [ ] Every spec has corresponding test (1:1)
- [ ] Test paths in YAML are correct
- [ ] All tests pass
- [ ] User approved all spec changes

**ANY UNCHECKED BOX = TASK INCOMPLETE**

---

## 🚨 VIOLATION PROTOCOLS

### Violation: "Fixing" Specs to Match Code

**WRONG:**
```
Test fails → "Spec is wrong" → Modify spec
```

**CORRECT:**
```
Test fails → "Code doesn't match contract" → Fix code
```

**IF spec actually wrong:**
```
Test fails → Ask user → Get approval → Modify spec
```

### Violation: Skipping Planning Phase

**WRONG:**
```
"Quick fix" → Write code → Tests fail unexpectedly
```

**CORRECT:**
```
Review specs → Identify gaps → Get approval → Write tests → Implement
```

### Violation: Testing Implementation Details

**WRONG:**
```yaml
acceptanceCriteria: StateManager stores states in associative array
criticality: constraint
```

**CORRECT:**
```yaml
acceptanceCriteria: StateManager provides O(1) state lookup by name
criticality: constraint
intent: Enables efficient state queries in machines with many states
```

---

## 🔍 REFERENCE COMMANDS

```bash
# View component specs
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

---

## 🔗 SKILL INTEGRATION

**Load with these skills:**
- **testing** - Test implementation
- **core-development** - Implementation guidance
- **documentation** - Document spec changes
- **region-development** - Machine spec patterns

---

## 📚 EXAMPLES FROM PROJECT

### Example: Spec-Driven Bug Fix

**Bug**: "Child regions don't update state when parent triggers"

**Protocol execution:**
1. ✅ Created spec in `specs/core/region.yaml`
2. ✅ Created failing test: `ConnectedRegionsStateUpdateTest.php`
3. ✅ Fixed `Region::processOneAction()`
4. ✅ Test passes, regression passes
5. ✅ Documented in `BUG_CONNECTED_REGIONS_STATE_UPDATE.md`

### Example: Spec Consolidation

**Before**: 7 granular specs (extract, apply, register steps)
**After**: 2 consolidated specs:
- "ProcessArray builds valid regions from configuration"
- "ProcessArray validates configuration before building"

**Result**: Clearer contracts, better coverage, less maintenance
