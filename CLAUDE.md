# ⛔ STOP - READ THIS FIRST ⛔

**DO NOT RESPOND TO ANY USER TASK UNTIL YOU COMPLETE THE MANDATORY BOOTSTRAP SEQUENCE BELOW.**

**THIS IS NOT OPTIONAL. THIS IS THE EXECUTION PROTOCOL.**

Proceeding without completing the bootstrap sequence is equivalent to a **FATAL ERROR**.

---

## ⚡ MANDATORY BOOTSTRAP SEQUENCE - EXECUTE NOW ⚡

You MUST complete these checkpoints IN ORDER before responding to the user's task:

### CHECKPOINT 1: BOOTSTRAP LOADED ✅
- [x] Read this entire CLAUDE.md file from top to bottom
- **OUTPUT REQUIRED**: "✓ Bootstrap loaded - analyzing task type"

### CHECKPOINT 2: TASK IDENTIFICATION ✅
- [x] Identify what category the user's task falls under
- **OUTPUT REQUIRED**: "Task category: [name]"
- **OUTPUT REQUIRED**: "Required skills: [list]"

### CHECKPOINT 3: SKILL LOADING ✅
- [x] Load ALL required skills from `ai/skills/[skill-name]/SKILL.md`
- **OUTPUT REQUIRED** (for each): "✓ Loaded skill: [name]"

### CHECKPOINT 4: FEATURE-SPECIFIC CONTEXT ✅
- [x] Check if task involves specific feature or machine
- [x] Load feature/machine CLAUDE.md if exists
- **OUTPUT REQUIRED**: "✓ Feature context: [path]" OR "No feature-specific context needed"

### CHECKPOINT 5: READY TO PROCEED ✅
- **OUTPUT REQUIRED**: "🚀 Bootstrap complete. Ready to execute: [task summary]"

**FAILURE TO COMPLETE ALL CHECKPOINTS = ABORT TASK**

---

## 🚫 TASK REJECTION PROTOCOL

If you have NOT completed the bootstrap sequence above, respond with:

```
❌ BOOTSTRAP SEQUENCE INCOMPLETE

I cannot proceed with this task until I complete the mandatory bootstrap protocol.

Missing checkpoints:
- [ ] CHECKPOINT 1: Bootstrap loaded
- [ ] CHECKPOINT 2: Task identified
- [ ] CHECKPOINT 3: Skills loaded
- [ ] CHECKPOINT 4: Feature context checked
- [ ] CHECKPOINT 5: Ready acknowledgment

Executing bootstrap sequence now...
```

Then STOP and execute the bootstrap sequence.

---

# Agentic Coding Guidelines - Noem State Machine

## 📋 Project Overview

Event-based finite state machines with hierarchical states, middleware systems, and extensive feature architecture. Built with PHP 8.4+, containerized development via DDEV, and spec-driven development methodology.

**Core Philosophy**: Specifications define WHAT (intent, behavior, API), not HOW (implementation, internals). Specs are executable contracts - when tests fail, fix code, never specs (unless user approves).

---

## 🎯 SKILL LOADING DECISION TREE

**YOU MUST USE THIS DECISION TREE TO LOAD SKILLS**

Start at the top and follow the tree based on your task:

```
📍 START HERE → What is the primary task category?

├─ 📋 PLANNING NEW FEATURES?
│  ├─ MANDATORY FIRST: Load `ai/skills/specification/SKILL.md`
│  ├─ OUTPUT: "Loading specification skill (mandatory for planning)"
│  └─ THEN: Determine additional skills needed after spec planning
│
├─ 🧪 WRITING OR RUNNING TESTS?
│  ├─ LOAD: `ai/skills/testing/SKILL.md`
│  └─ OUTPUT: "Loading testing skill for test operations"
│
├─ 🔧 CORE DEVELOPMENT (Chain/Middleware/Features/RegionBuilder)?
│  ├─ LOAD: `ai/skills/core-development/SKILL.md`
│  └─ OUTPUT: "Loading core-development skill for internal architecture work"
│
├─ 🎰 STATE MACHINES (YAML definitions/machines/)?
│  ├─ LOAD: `ai/skills/region-development/SKILL.md`
│  └─ OUTPUT: "Loading region-development skill for state machine work"
│
└─ 📝 DOCUMENTATION (CLAUDE.md/README/docs)?
   ├─ LOAD: `ai/skills/documentation/SKILL.md`
   └─ OUTPUT: "Loading documentation skill for documentation work"
```

### Multiple Skills Required

Some tasks require loading MULTIPLE skills in sequence:

**Adding New Feature (MANDATORY SEQUENCE):**
1. LOAD `specification` - Plan specs first (NEVER skip)
2. LOAD `testing` - Create tests (red phase)
3. LOAD `core-development` - Implement feature
4. OUTPUT: "Loaded skills in sequence: specification → testing → core-development"

**Creating New Machine:**
1. LOAD `region-development` - Write YAML definition
2. LOAD `specification` - Create machine specs
3. LOAD `testing` - Write E2E tests
4. OUTPUT: "Loaded skills in sequence: region-development → specification → testing"

**Bug Fixes:**
1. LOAD `specification` - Create spec for bug (MANDATORY)
2. LOAD `testing` - Write failing test
3. LOAD appropriate development skill based on component
4. OUTPUT: "Loaded skills in sequence: specification → testing → [development skill]"

---

## 🔍 FEATURE/MACHINE-SPECIFIC CONTEXT PROTOCOL

**EXECUTE THIS PROTOCOL AFTER LOADING SKILLS:**

```
STEP 1: Identify if task involves specific component
  ├─ Does task mention feature in /src/Feature/{FeatureName}/?
  │  └─ YES → Check for /src/Feature/{FeatureName}/CLAUDE.md
  │           ├─ EXISTS → LOAD IT NOW
  │           │          OUTPUT: "✓ Loaded feature context: [path]"
  │           └─ MISSING → OUTPUT: "No feature-specific context found"
  │
  └─ Does task mention machine in /machines/{machine-name}/?
     └─ YES → Check for /machines/{machine-name}/CLAUDE.md
              ├─ EXISTS → LOAD IT NOW
              │          OUTPUT: "✓ Loaded machine context: [path]"
              └─ MISSING → OUTPUT: "No machine-specific context found"

STEP 2: If neither applies
  └─ OUTPUT: "No component-specific context required"
```

**Feature/machine-level CLAUDE.md files override general guidance.**

---

## 🚨 CRITICAL DEVELOPMENT RULES - NEVER VIOLATE

These rules are **NON-NEGOTIABLE**:

### 🔴 SPEC-FIRST PARADIGM (ABSOLUTE)

1. ⛔ **NEVER write code without specs first**
   - Violation = STOP and create specs
   - No exceptions, no shortcuts

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
   - No bug fix without specification

5. ⛔ **NEVER modify /vendor/ directory**
   - External dependencies are read-only
   - Never patch packages directly
   - Ask user before modifying any dependency

### 🟡 MANDATORY WORKFLOW (ENFORCE STRICTLY)

```
User Request
    ↓
STOP: Plan Specs (get user approval)
    ↓
Create Tests (red phase - tests must fail)
    ↓
Implement (iterate until tests pass)
    ↓
All Tests Pass (green phase)
    ↓
Quality Check (composer quality)
    ↓
Done
```

**You MUST NOT skip any step in this workflow.**

---

## 🏗️ Main Building Blocks

### Architecture & Stack

- **PHP 8.4+** - Primary programming language with modern features
- **DDEV** - Containerized local development environment (prefix all commands with `ddev exec`)
- **PSR-4** - Autoloading standards
- **Composer** - Dependency management
- **PHPUnit** - Testing framework with custom spec runners
- **Middleware Pattern** - Extensible processing chains

### Directory Structure

```
/
├── src/                    # Source code (✅ ALLOWED: modify freely)
│   ├── Region.php         # State machine runtime
│   ├── RegionBuilder.php  # Fluent construction API
│   ├── Feature/           # Modular feature extensions
│   ├── Chains/            # Middleware pipelines
│   └── Middleware/        # ChainMail, Chain, Mesh
├── specs/                  # YAML acceptance criteria (⛔ USER APPROVAL REQUIRED)
│   ├── core/              # Region, RegionBuilder
│   ├── chain/             # Middleware
│   ├── features/          # Features (Transitions, Loader, etc.)
│   └── machines/          # Complete applications (E2E)
├── tests/PHPUnit/         # Test suite (✅ ALLOWED: create/modify)
│   ├── Unit/              # Unit tests
│   ├── Integration/       # Integration tests
│   └── E2E/               # Machine tests (end-to-end)
├── machines/              # Example state machine applications
├── ai/skills/             # Specialized skill documentation (📖 READ ONLY)
├── docs/                  # Additional documentation
└── vendor/                # Dependencies (🚫 NEVER MODIFY)
```

### Key Configuration Files

- `composer.json` - Dependencies and scripts
- `phpunit.xml.dist` - PHPUnit configuration
- `phpcs.xml.dist` - Code style standards
- `psalm.xml` - Static analysis configuration
- `.ddev/config.yaml` - DDEV environment setup

---

## 🎛️ Feature Overview

The project uses a modular feature system where features extend RegionBuilder capabilities:

| Feature                | Purpose                                                 | Critical Notes                   |
|------------------------|---------------------------------------------------------|----------------------------------|
| **TransitionsFeature** | Automatic state transitions with guard conditions       | -                                |
| **ExtendedState**      | Context data scoped to states/regions                   | ⚠️ MUST load before AsyncFeature |
| **AsyncFeature**       | Coroutine-based async operations with task scheduling   | Requires ExtendedState first     |
| **RegionLoader**       | Load machines from YAML/array configurations            | Often loaded first               |
| **TemplateFeature**    | Dynamic content generation from templates               | -                                |
| **AiFeature**          | AI integration for dynamic content generation           | -                                |
| **SpawnFeature**       | Dynamic child region creation with lifecycle management | -                                |

**⚠️ CRITICAL**: Feature order matters! Features wrap each other in LIFO order. ExtendedState MUST come before AsyncFeature.

---

## ✅ Code Modification Rules

### ALLOWED - Internal Code
- ✅ Modify any code in `/src/` directory
- ✅ Update internal dependencies
- ✅ Add new features, middleware, or components
- ✅ Create machine-specific implementations
- ✅ Create/modify tests in `/tests/PHPUnit/`

### PROHIBITED - External Code
- 🚫 Modify code in `/vendor/` directory
- 🚫 Patch external packages directly
- 🚫 Change package code without user approval

### REQUIRES USER APPROVAL
- ⚠️ Modifying any YAML file in `/specs/`
- ⚠️ Changing spec behavior or acceptance criteria
- ⚠️ Removing or consolidating specs

---

## 🎓 Additional Resources

After loading skills, you can reference these for deeper context:

- [Project README](./README.md) - Setup and quick start
- [Core Development Skill](ai/skills/core-development/SKILL.md) - Chain, Middleware, Features
- [Region Development Skill](ai/skills/region-development/SKILL.md) - YAML, RegionBuilder, machines
- [Specification Skill](ai/skills/specification/SKILL.md) - Spec-driven workflow
- [Testing Skill](ai/skills/testing/SKILL.md) - Test infrastructure and patterns
- [Documentation Skill](ai/skills/documentation/SKILL.md) - Documentation standards

---

## 🤖 AI Agent Protocol

**These are your operating instructions:**

### MANDATORY PROTOCOLS

1. ✅ **Always complete bootstrap sequence first**
   - Never respond to tasks without completing checkpoints
   - Output required acknowledgments at each checkpoint
   - Load skills using the decision tree above

2. ✅ **Load skills based on task type**
   - Use decision tree, not guesses
   - Load multiple skills when needed
   - Output which skills you're loading and why

3. ✅ **Check for component-specific CLAUDE.md**
   - Execute the feature/machine context protocol
   - Load and follow component-specific instructions
   - These override general guidance

4. ✅ **Respect spec-first paradigm**
   - Workflow: Plan Specs → Get Approval → Test → Implement
   - Never skip planning phase
   - Never modify specs without approval

5. ✅ **Maintain ONE SPEC = ONE TEST CLASS**
   - Never consolidate specs
   - Never split specs
   - Maintain strict 1:1 mapping

6. ✅ **Use DDEV for all commands**
   - Prefix with `ddev exec`
   - Never run commands outside container
   - Ensure consistency across environments

7. ✅ **Quality before completion**
   - Run `ddev exec composer quality`
   - Fix all issues before marking task complete
   - No exceptions

8. ✅ **When stuck, ASK**
   - Don't guess implementation details
   - Don't assume user requirements
   - Ask for clarification explicitly

9. ✅ **Skills are HOW, CLAUDE.md is WHAT**
   - Skills provide procedural guides
   - CLAUDE.md provides context and constraints
   - Follow both together

10. ✅ **Load multiple skills when needed**
    - Complex tasks require multiple perspectives
    - Use the decision tree to identify all required skills
    - Load them in logical sequence

### STARTING NEW SESSION

Execute this protocol at session start:

```
STEP 1: Review context
  ├─ Run: git log --oneline -10
  └─ OUTPUT: Brief summary of recent changes

STEP 2: Baseline status
  ├─ Run: ddev atlas
  └─ OUTPUT: Current test status

STEP 3: Ask user for context
  └─ OUTPUT: "What would you like me to work on?"
```

### WHEN STUCK

Execute this protocol when stuck:

```
STEP 1: Ask user for clarification
  └─ Don't guess, don't assume, ASK

STEP 2: Review existing patterns
  ├─ Check specs for similar examples
  └─ Check machines/ for usage patterns

STEP 3: Load additional skills if needed
  └─ Reassess which skills might help

STEP 4: When in doubt
  └─ Propose spec consolidation over adding specs
```

---

## 📊 Task Category Decision Guide

Use this guide to identify task category for CHECKPOINT 2:

| User Says... | Task Category | Required Skills |
|--------------|---------------|-----------------|
| "Add feature X" | Planning → Development | specification, testing, core-development |
| "Fix bug in Y" | Planning → Development | specification, testing, [component skill] |
| "Create machine for Z" | Machine Development | region-development, specification, testing |
| "Run tests" | Testing | testing |
| "Update docs" | Documentation | documentation |
| "Refactor X" | Development | [component skill], testing |
| "How does X work?" | Research | [component skill] |
| "Write spec for X" | Specification | specification |

---

## 🎯 Final Checkpoint Before Task Execution

Before you begin ANY task, verify:

- [ ] Bootstrap sequence completed (all 5 checkpoints)
- [ ] Required skills loaded and acknowledged
- [ ] Feature/machine context checked
- [ ] Critical rules reviewed
- [ ] Workflow understood
- [ ] Ready acknowledgment outputted

**If any box is unchecked → STOP and complete it now.**
