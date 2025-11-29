# Documentation Skill - Documentation Standards & Maintenance

## Purpose

This skill covers **documentation standards and best practices** for the Regions project, including README maintenance, AGENTS.md files, inline documentation, and API documentation.

## Documentation Types

### 1. AGENTS.md Files

#### Root-Level AGENTS.md

**Purpose**: Bootstrap context for AI agents. Should be lean and directive-focused.

**Structure**:
```markdown
# Project Name - AI Agent Guide

## Purpose
Brief description of the project

## Conditional Skill Loading
Instructions for loading appropriate skills based on task type

## Project Overview
High-level architecture and key concepts

## Feature Overview
Concise list of available features with one-sentence descriptions

## Critical Principles
Spec-driven development emphasis and core rules

## Directory Structure
Overview of project organization

## Reference
Quick-reference tables and commands

## When to Load Skills
Clear triggers for loading each skill
```

**Key Principles**:
- Keep concise (~200-300 lines)
- Focus on WHAT and WHEN, not HOW
- Direct to appropriate skills for HOW
- Emphasize spec-driven development
- No maintenance instructions (move to documentation skill)

#### Feature-Level AGENTS.md

**Location**: `machines/{machine-name}/AGENTS.md` or `src/Feature/{FeatureName}/AGENTS.md`

**Purpose**: Feature-specific context and instructions.

**When to Create**:
- Complex machines with unique patterns
- Features with non-obvious usage
- Components with special requirements

**Structure**:
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

### 2. README.md Files

#### Project README

**Purpose**: Human-readable project overview and setup guide.

**Required Sections**:
1. **Overview** - What the project is
2. **Installation** - Setup instructions
3. **Quick Start** - Basic usage example
4. **Features** - Key capabilities
5. **Documentation** - Links to further docs
6. **Contributing** - How to contribute
7. **License** - License information

**Example Structure**:
```markdown
# Regions - Event-Based State Machines

Event-based finite state machines with hierarchical states, middleware, and feature system.

## Installation

```bash
composer require noem/state
```

## Quick Start

```php
use Noem\State\RegionBuilder;

$region = (new RegionBuilder())
    ->setStates('idle', 'active', 'done')
    ->markInitial('idle')
    ->build();
```

## Features

- **Hierarchical States** - Nested state machines
- **Middleware System** - Extensible processing chains
- **Feature Architecture** - Modular capabilities
- **Async Support** - Coroutine-based operations
- **YAML Configuration** - Declarative machine definitions

## Documentation

- [User Guide](docs/user-guide.md)
- [API Reference](docs/api-reference.md)
- [Examples](machines/)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md)

## License

MIT License - see [LICENSE](LICENSE)
```

#### Feature READMEs

**Location**: `src/Feature/{FeatureName}/README.md`

**Purpose**: Feature-specific usage guide.

**Sections**:
1. **Overview** - Feature purpose
2. **Installation** - If feature is optional
3. **Usage** - How to use
4. **Configuration** - Available options
5. **Examples** - Code samples
6. **API Reference** - Public methods

### 3. Inline Documentation

#### PHPDoc Standards

**Class Documentation**:
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

**Method Documentation**:
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

**Property Documentation**:
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

**When to Use**:
- Complex algorithms
- Non-obvious behavior
- Critical implementation details
- Workarounds for bugs

**Style**:
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

**Link to Specs**:
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

### 4. Specification Documentation

#### YAML Specs

**Purpose**: Executable contracts defining behavior.

**Critical Fields**:
- `acceptanceCriteria` - Observable behavior
- `criticality` - contract|constraint|detail
- `intent` - Why this matters
- `test` - Path to test file

**Documentation Guidelines**:
1. **Intent is critical** - Must explain business value
2. **Describe WHAT, not HOW** - Observable behavior only
3. **Be specific** - Testable and unambiguous
4. **Avoid implementation details** - Focus on contracts

**Example**:
```yaml
features:
  - name: state-management
    specs:
      - acceptanceCriteria: A region can check if it is in a specific state
        criticality: contract
        intent: |
          Enables conditional logic in applications, allowing consumers to
          guard operations, validate preconditions, and implement state-
          dependent behavior without accessing internal state representation.
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Region/StateCheckTest.php
```

## Documentation Maintenance

### Boy Scout Rule

**Always improve documentation when touching code**:

✅ **Do**:
- Update stale examples
- Fix broken links
- Clarify confusing sections
- Add missing PHPDoc
- Update version numbers

❌ **Don't**:
- Leave outdated information
- Ignore broken examples
- Skip documentation for new features
- Remove documentation without replacement

### Keeping AGENTS.md Updated

#### Root AGENTS.md Changes

**When to Update**:
- New skill added
- Project structure changes
- Core principles change
- New critical patterns emerge

**Update Process**:
1. Identify what changed
2. Update relevant section
3. Keep it concise (offload to skills if detailed)
4. Verify all skill references are correct
5. Check conditional loading triggers

#### Skill File Changes

**When to Update**:
- New pattern discovered
- Common pitfall identified
- Best practice emerges
- API changes
- Examples become stale

**Update Process**:
1. Add to appropriate section
2. Include code examples
3. Link to related skills
4. Update "When to Load" section if triggers change

### README Maintenance

#### Version Updates

When releasing new versions:
- Update installation instructions
- Update version numbers in examples
- Add new features to feature list
- Update compatibility notes

#### Example Maintenance

**Regular Checks**:
- [ ] Examples run without errors
- [ ] Examples use current API
- [ ] Examples follow current best practices
- [ ] Examples demonstrate key features

### Specification Maintenance

#### When Specs Change

**Always**:
1. Get user approval first
2. Update YAML file
3. Update corresponding test
4. Update test path in YAML
5. Run spec suite to verify
6. Document reason for change in commit

#### Consolidation

When consolidating specs:
1. Propose consolidation to user
2. Create new consolidated spec
3. Update test to match new spec
4. Remove old specs
5. Update spec count in documentation

## Documentation Standards

### Markdown Style

**Headers**:
```markdown
# H1 - Top-level sections
## H2 - Major sections
### H3 - Subsections
#### H4 - Minor subsections (use sparingly)
```

**Code Blocks**:
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

**Lists**:
```markdown
- Unordered lists for items without sequence
- Use for feature lists, capabilities, options

1. Ordered lists for sequential steps
2. Use for instructions, procedures, algorithms
```

**Tables**:
```markdown
| Column 1 | Column 2 | Column 3 |
|----------|----------|----------|
| Data 1   | Data 2   | Data 3   |
| Data 4   | Data 5   | Data 6   |
```

**Links**:
```markdown
[Link text](path/to/file.md)
[External link](https://example.com)
[Section link](#section-anchor)
```

### PHP Documentation Style

**Use PHPStan/Psalm Types**:
```php
/**
 * @param array<string, mixed> $context
 * @param list<Region> $connections
 * @return array{state: string, final: bool}
 */
```

**Nullable Types**:
```php
/**
 * @param string|null $initial Optional initial state
 * @return Region|null Returns null if build fails
 */
```

**Union Types**:
```php
/**
 * @param string|int $identifier State name or index
 * @param callable(object): bool $guard Guard predicate
 */
```

### YAML Documentation Style

**Comments**:
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

**Intent Formatting**:
```yaml
intent: |
  Multi-line intent with proper formatting.
  
  Can include paragraphs and detailed explanations
  of why this behavior matters to users.
```

## Common Documentation Tasks

### Task: Add New Feature

**Checklist**:
- [ ] Create feature README (`src/Feature/NewFeature/README.md`)
- [ ] Add feature to project README feature list
- [ ] Create or update feature-level AGENTS.md if complex
- [ ] Add feature overview to root AGENTS.md
- [ ] Document in appropriate skill file
- [ ] Add PHPDoc to all public methods
- [ ] Create usage examples
- [ ] Add to integration tests

### Task: Fix Bug

**Checklist**:
- [ ] Add missing spec (if spec gap)
- [ ] Update relevant documentation
- [ ] Add to "Common Pitfalls" if applicable
- [ ] Update inline comments explaining fix
- [ ] Document in commit message

### Task: Refactor Code

**Checklist**:
- [ ] Update affected README sections
- [ ] Update inline documentation
- [ ] Update examples using old API
- [ ] Check for stale references
- [ ] Update skill files if patterns changed

### Task: Create New Machine

**Checklist**:
- [ ] Create machine README (`machines/{name}/README.md`)
- [ ] Create machine-level AGENTS.md if complex
- [ ] Add machine to examples list in project README
- [ ] Document container configuration
- [ ] Add usage examples
- [ ] Document any unique patterns

## Integration with Other Skills

### With Specification Skill

- Ensure specs have compelling intent
- Link tests to specs in documentation
- Document spec-driven workflow

### With Testing Skill

- Document test patterns
- Link test documentation to specs
- Explain test organization

### With Core Development Skill

- Document architectural decisions
- Explain design patterns
- Reference implementation details

### With Region Development Skill

- Document machine patterns
- Explain YAML schema
- Provide usage examples

## Quality Checks

### Before Committing Documentation

**Checklist**:
- [ ] Markdown renders correctly
- [ ] All links work
- [ ] Code examples run
- [ ] PHPDoc is accurate
- [ ] Spelling is correct
- [ ] Formatting is consistent

### Periodic Audits

**Quarterly Reviews**:
- [ ] Update version numbers
- [ ] Verify examples still work
- [ ] Check for outdated sections
- [ ] Update external links
- [ ] Consolidate redundant information

## Tools & Automation

### Markdown Linting

```bash
# Check markdown syntax
markdownlint docs/**/*.md

# Fix automatically where possible
markdownlint --fix docs/**/*.md
```

### Link Checking

```bash
# Verify all links are valid
markdown-link-check docs/**/*.md
```

### PHPDoc Validation

```bash
# Check documentation coverage
phpdoc --validate
```

## When to Load This Skill

**Always load when:**
- Writing or updating documentation
- Creating README files
- Maintaining AGENTS.md files
- Adding inline code comments
- Documenting architectural decisions
- Creating examples

**Combine with:**
- **specification** skill - for spec documentation
- **testing** skill - for test documentation  
- **core-development** skill - for API documentation
- **region-development** skill - for usage documentation

## Reference

### Documentation Files in Project

```
/
├── README.md                           # Project overview
├── AGENTS.md                           # AI agent bootstrap
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
        └── AGENTS.md                   # Optional machine docs
```

### External Documentation

- [PHPDoc Reference](https://docs.phpdoc.org/)
- [Markdown Guide](https://www.markdownguide.org/)
- [PSR-5 PHPDoc Standard](https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md)
