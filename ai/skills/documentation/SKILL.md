# Documentation Skill - Documentation Standards Protocol

## ⚡ CRITICAL DOCUMENTATION RULES

**Boy Scout Rule** - Always improve documentation when touching code. NEVER leave outdated information. NEVER skip documentation for new features.

---

## 🚫 ABSOLUTE RULES - NEVER VIOLATE

| Rule | Violation = Consequence |
|------|-------------------------|
| **NEVER leave outdated information** | STOP → Update immediately |
| **NEVER skip docs for new features** | STOP → Document before completion |
| **NEVER remove docs without replacement** | STOP → Replace or update first |
| **Intent MUST be compelling** | STOP → Rewrite until business value clear |
| **Examples MUST run without errors** | STOP → Test and fix examples |

---

## 📋 DOCUMENTATION TYPES REFERENCE

### CLAUDE.md Files

#### Root-Level CLAUDE.md

**Purpose**: Bootstrap context for AI agents. Lean and directive-focused.

**MANDATORY Structure:**

```markdown
# Project Name - AI Agent Guide

## Purpose
Brief description

## Conditional Skill Loading
Decision tree for loading skills based on task type

## Project Overview
High-level architecture and key concepts

## Feature Overview
Concise list with one-sentence descriptions

## Critical Principles
Spec-driven development emphasis, core rules

## Directory Structure
Project organization overview

## Reference
Quick-reference tables and commands

## When to Load Skills
Clear triggers for loading each skill
```

**Constraints:**
- Keep concise (~200-300 lines)
- Focus on WHAT and WHEN, not HOW
- Direct to skills for HOW
- Emphasize spec-driven development

#### Feature-Level CLAUDE.md

**Location**: `machines/{machine-name}/CLAUDE.md` or `src/Feature/{FeatureName}/CLAUDE.md`

**When to create:**
- Complex machines with unique patterns
- Features with non-obvious usage
- Components with special requirements

**MANDATORY Structure:**

```markdown
# Feature Name - Agent Instructions

## Purpose
What this feature/machine does

## Key Concepts
Feature-specific terminology and patterns

## Usage Patterns
How to use this feature

## Common Pitfalls
Known issues and how to avoid them

## Examples
Concrete usage examples

## Integration Points
How this connects with other parts
```

### README.md Files

#### Project README

**MANDATORY Sections:**
1. **Overview** - What the project is
2. **Installation** - Setup instructions
3. **Quick Start** - Basic usage example
4. **Features** - Key capabilities
5. **Documentation** - Links to further docs
6. **Contributing** - How to contribute
7. **License** - License information

#### Feature READMEs

**Location**: `src/Feature/{FeatureName}/README.md`

**MANDATORY Sections:**
1. **Overview** - Feature purpose
2. **Installation** - If feature is optional
3. **Usage** - How to use
4. **Configuration** - Available options
5. **Examples** - Code samples
6. **API Reference** - Public methods

### Inline Documentation

#### PHPDoc Standards

**Class documentation:**

```php
/**
 * Event-based finite state machine with hierarchical state support.
 *
 * Provides lifecycle callbacks, event dispatch, and connected region management.
 * Regions can be composed hierarchically for complex state machine architectures.
 *
 * @package Noem\State
 */
class Region
{
    // ...
}
```

**Method documentation:**

```php
/**
 * Trigger an event in the state machine.
 *
 * Dispatches the event to action callbacks, evaluates guards, and executes
 * transitions if conditions are met. Propagates events to connected child regions.
 *
 * @param object $payload Event payload containing trigger data
 *
 * @return void
 *
 * @throws \RuntimeException If the region is in a final state
 */
public function trigger(object $payload): void
{
    // ...
}
```

**Property documentation:**

```php
/**
 * @var array<string, array<string, array<callable>>> State → Event → Callbacks map
 */
private array $actions = [];

/**
 * @var list<Region> Connected child regions that receive propagated events
 */
private array $connections = [];
```

#### Inline Comments

**When to use:**
- Complex algorithms
- Non-obvious behavior
- Critical implementation details
- Workarounds for bugs

**Style:**

```php
// Use single-line comments for brief explanations
$result = $this->processAction($trigger);

/**
 * Multi-line comments for longer explanations.
 *
 * This algorithm implements the LIFO middleware pattern where
 * the last registered middleware executes first, creating a
 * "Russian doll" wrapping pattern.
 */
foreach ($middleware as $layer) {
    $result = $layer($result, $next);
}
```

#### Test Documentation

**MANDATORY - Link to specs:**

```php
/**
 * Acceptance Criterion: A region can check if it is in a specific state
 *
 * @see specs/core/region.yaml
 */
#[Group('region'), Group('state-management')]
class StateCheckTest extends TestCase
{
    /**
     * @test
     */
    public function regionCanCheckCurrentState(): void
    {
        // Test implementation
    }
}
```

### Specification Documentation

**Critical fields:**
- `acceptanceCriteria` - Observable behavior
- `criticality` - contract|constraint|detail
- `intent` - Why this matters
- `test` - Path to test file

**Guidelines:**
1. **Intent is CRITICAL** - Must explain business value
2. **Describe WHAT, not HOW** - Observable behavior only
3. **Be specific** - Testable and unambiguous
4. **Avoid implementation details** - Focus on contracts

---

## 🔧 DOCUMENTATION MAINTENANCE PROTOCOL

### Boy Scout Rule (MANDATORY)

**Execute when touching any code:**

```
STEP 1: Check documentation age
  ├─ Examples outdated?
  ├─ Links broken?
  ├─ Information stale?
  └─ Missing PHPDoc?

STEP 2: Update immediately
  └─ Fix all issues found

STEP 3: Never leave worse than found
  └─ Always improve
```

**DO:**
- ✅ Update stale examples
- ✅ Fix broken links
- ✅ Clarify confusing sections
- ✅ Add missing PHPDoc
- ✅ Update version numbers

**DON'T:**
- ❌ Leave outdated information
- ❌ Ignore broken examples
- ❌ Skip documentation for new features
- ❌ Remove documentation without replacement

### CLAUDE.md Updates

#### Root CLAUDE.md Changes

**When to update:**
- New skill added
- Project structure changes
- Core principles change
- New critical patterns emerge

**Update protocol:**

```
STEP 1: Identify what changed
STEP 2: Update relevant section
STEP 3: Keep concise (offload to skills if detailed)
STEP 4: Verify all skill references correct
STEP 5: Check conditional loading triggers
```

#### Skill File Changes

**When to update:**
- New pattern discovered
- Common pitfall identified
- Best practice emerges
- API changes
- Examples become stale

**Update protocol:**

```
STEP 1: Add to appropriate section
STEP 2: Include code examples
STEP 3: Link to related skills
STEP 4: Update "When to Load" if triggers change
```

### README Maintenance

#### Version Updates

**Execute when releasing:**
- Update installation instructions
- Update version numbers in examples
- Add new features to feature list
- Update compatibility notes

#### Example Maintenance

**MANDATORY regular checks:**
- [ ] Examples run without errors
- [ ] Examples use current API
- [ ] Examples follow current best practices
- [ ] Examples demonstrate key features

### Specification Maintenance

**Protocol when specs change:**

```
STEP 1: Get user approval - MANDATORY
STEP 2: Update YAML file
STEP 3: Update corresponding test
STEP 4: Update test path in YAML
STEP 5: Run spec suite to verify
STEP 6: Document reason for change in commit
```

**Consolidation protocol:**

```
STEP 1: Propose consolidation to user
STEP 2: Create new consolidated spec
STEP 3: Update test to match new spec
STEP 4: Remove old specs
STEP 5: Update spec count in documentation
```

---

## 📝 DOCUMENTATION STANDARDS

### Markdown Style

**Headers:**
```markdown
# H1 - Top-level sections
## H2 - Major sections
### H3 - Subsections
#### H4 - Minor subsections (use sparingly)
```

**Code blocks:**
````markdown
```php
// PHP code with syntax highlighting
$region = new Region();
```

```yaml
# YAML configuration
name: example
states: [idle, active]
```

```bash
# Shell commands
composer install
```
````

**Lists:**
```markdown
- Unordered lists for items without sequence
- Use for feature lists, capabilities, options

1. Ordered lists for sequential steps
2. Use for instructions, procedures, algorithms
```

**Tables:**
```markdown
| Column 1 | Column 2 | Column 3 |
|----------|----------|----------|
| Data 1   | Data 2   | Data 3   |
| Data 4   | Data 5   | Data 6   |
```

**Links:**
```markdown
[Link text](path/to/file.md)
[External link](https://example.com)
[Section link](#section-anchor)
```

### PHP Documentation Style

**Use PHPStan/Psalm types:**

```php
/**
 * @param array<string, mixed> $context
 * @param list<Region> $connections
 * @return array{state: string, final: bool}
 */
```

**Nullable types:**

```php
/**
 * @param string|null $initial Optional initial state
 * @return Region|null Returns null if build fails
 */
```

**Union types:**

```php
/**
 * @param string|int $identifier State name or index
 * @param callable(object): bool $guard Guard predicate
 */
```

### YAML Documentation Style

**Comments:**

```yaml
# Top-level comment explaining section
name: machine_name

states:
  - name: idle
    on:
      # Event-specific comment
      event:
        - handler: action.handler  # Inline comment
```

**Intent formatting:**

```yaml
intent: |
  Multi-line intent with proper formatting.

  Can include paragraphs and detailed explanations
  of why this behavior matters to users.
```

---

## ✅ DOCUMENTATION TASK PROTOCOLS

### Task: Add New Feature

**MANDATORY Checklist:**
- [ ] Create feature README (`src/Feature/NewFeature/README.md`)
- [ ] Add feature to project README feature list
- [ ] Create or update feature-level CLAUDE.md if complex
- [ ] Add feature overview to root CLAUDE.md
- [ ] Document in appropriate skill file
- [ ] Add PHPDoc to all public methods
- [ ] Create usage examples
- [ ] Add to integration tests

### Task: Fix Bug

**MANDATORY Checklist:**
- [ ] Add missing spec (if spec gap)
- [ ] Update relevant documentation
- [ ] Add to "Common Pitfalls" if applicable
- [ ] Update inline comments explaining fix
- [ ] Document in commit message

### Task: Refactor Code

**MANDATORY Checklist:**
- [ ] Update affected README sections
- [ ] Update inline documentation
- [ ] Update examples using old API
- [ ] Check for stale references
- [ ] Update skill files if patterns changed

### Task: Create New Machine

**MANDATORY Checklist:**
- [ ] Create machine README (`machines/{name}/README.md`)
- [ ] Create machine-level CLAUDE.md if complex
- [ ] Add machine to examples list in project README
- [ ] Document container configuration
- [ ] Add usage examples
- [ ] Document any unique patterns

---

## 🔗 SKILL INTEGRATION

**Load with these skills:**
- **specification** - Spec documentation
- **testing** - Test documentation
- **core-development** - API documentation
- **region-development** - Usage documentation

---

## ✅ PRE-COMMIT QUALITY PROTOCOL

**MANDATORY before committing documentation:**

```
CHECK 1: Markdown renders correctly
  └─ Verify all formatting

CHECK 2: All links work
  └─ Test all internal and external links

CHECK 3: Code examples run
  └─ Test all code samples

CHECK 4: PHPDoc accurate
  └─ Verify all type annotations

CHECK 5: Spelling correct
  └─ Check for typos

CHECK 6: Formatting consistent
  └─ Follow established patterns
```

**ANY CHECK FAILS = DO NOT COMMIT**

---

## 📂 DOCUMENTATION FILES REFERENCE

```
/
├── README.md                           # Project overview
├── CLAUDE.md                           # AI agent bootstrap
├── CONTRIBUTING.md                     # Contribution guide
├── LICENSE                             # License file
├── ai/
│   └── skills/
│       ├── specification/SKILL.md      # Spec-driven development
│       ├── testing/SKILL.md            # Testing patterns
│       ├── core-development/SKILL.md   # Core library development
│       ├── region-development/SKILL.md # Machine development
│       └── documentation/SKILL.md      # This file
├── docs/                               # Additional documentation
│   ├── user-guide.md
│   └── api-reference.md
├── specs/                              # Specifications (YAML)
│   ├── core/
│   ├── features/
│   └── machines/
├── src/                                # Source with PHPDoc
│   ├── Region.php
│   ├── RegionBuilder.php
│   └── Feature/
│       └── */README.md                 # Feature docs
└── machines/                           # Example machines
    └── */
        ├── README.md                   # Machine overview
        └── CLAUDE.md                   # Optional machine docs
```

---

## 🔍 EXTERNAL DOCUMENTATION

- [PHPDoc Reference](https://docs.phpdoc.org/)
- [Markdown Guide](https://www.markdownguide.org/)
- [PSR-5 PHPDoc Standard](https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md)
