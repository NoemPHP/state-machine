# Agent Handover Protocol - Spec-First Development

## Overview

This document defines the handover protocol between `spec-planner` and `core-development-expert` agents to enforce spec-first development workflow.

## Separation of Responsibilities

### spec-planner Agent (Planning Phase)

**Role**: Creates and manages specifications

**Responsibilities**:
- ✅ Create YAML specification files
- ✅ Get user approval for specifications
- ✅ Generate handover payload for implementation
- ✅ Modify existing specs (with user approval)
- ✅ Define WHAT components should do (behavior, API, contracts)

**Prohibited**:
- ❌ Write implementation code
- ❌ Create test files
- ❌ Implement features

**Output**: Handover payload + approved spec files

---

### core-development-expert Agent (Implementation Phase)

**Role**: Implements code from approved specifications

**Responsibilities**:
- ✅ Validate handover payload
- ✅ Create test files that fail (red phase)
- ✅ Implement code to make tests pass (green phase)
- ✅ Run quality checks
- ✅ Implement HOW specs are satisfied

**Prohibited**:
- ❌ Create specification files
- ❌ Modify specs without user approval
- ❌ Start work without handover payload

**Input Required**: Handover payload from spec-planner

---

## Handover Payload Format

### Structure

```json
{
  "agent": "core-development-expert",
  "handover_type": "approved_specification",
  "spec_files": [
    "specs/features/message.yaml",
    "specs/features/subscription.yaml"
  ],
  "component_type": "feature|chain|core|machine",
  "test_directory": "tests/PHPUnit/Unit/Feature/Message/",
  "implementation_files": [
    "src/Feature/Message/Message.php",
    "src/Feature/Message/MessageFeature.php"
  ],
  "summary": "MessageFeature - Call-response messaging with UUID correlation",
  "critical_notes": [
    "Requires SubscriptionFeature to be loaded first",
    "Array-only payload format enforced",
    "First-response-wins pattern (no multi-response support)"
  ]
}
```

### Field Definitions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `agent` | string | Yes | Must be "core-development-expert" |
| `handover_type` | string | Yes | Must be "approved_specification" |
| `spec_files` | array | Yes | Absolute paths to approved spec YAML files |
| `component_type` | enum | Yes | One of: feature, chain, core, machine |
| `test_directory` | string | Yes | Where to create test files |
| `implementation_files` | array | Yes | Expected implementation file paths |
| `summary` | string | Yes | Brief description of what's being implemented |
| `critical_notes` | array | No | Important implementation considerations |

---

## Workflow Sequence

### 1. User Requests New Feature

```
User: "I want to add a MessageFeature for request-response patterns"
```

### 2. Main Assistant Routes to spec-planner

```
Assistant: "This requires creating specifications first. Launching spec-planner agent..."

[Invokes spec-planner agent]
```

### 3. spec-planner Creates Specifications

```
spec-planner:
- Analyzes requirements
- Creates specs/features/message.yaml
- Presents to user for approval
- Waits for user approval
```

### 4. User Approves Specifications

```
User: "Approved, looks good"
```

### 5. spec-planner Creates Handover Payload

```
spec-planner:
"✅ Specification approved and written to specs/features/message.yaml

**HANDOVER TO core-development-expert**

I'm now transferring this task with the following approved specifications:
- Spec files: specs/features/message.yaml
- Component type: feature
- Test directory: tests/PHPUnit/Unit/Feature/Message/

The core-development-expert will now:
1. Create test class(es) that FAIL (red phase)
2. Implement code to make tests pass (green phase)
3. Run composer quality to verify

[Handover Payload]
{
  "agent": "core-development-expert",
  "handover_type": "approved_specification",
  "spec_files": ["specs/features/message.yaml"],
  "component_type": "feature",
  "test_directory": "tests/PHPUnit/Unit/Feature/Message/",
  "implementation_files": [
    "src/Feature/Message/Message.php",
    "src/Feature/Message/MessageFeature.php"
  ],
  "summary": "MessageFeature - Call-response messaging",
  "critical_notes": ["Requires SubscriptionFeature first"]
}
```

### 6. Main Assistant Launches core-development-expert

```
Assistant: "Launching core-development-expert with approved specification payload..."

[Invokes core-development-expert with handover payload]
```

### 7. core-development-expert Implements

```
core-development-expert:
1. Validates handover payload
2. Reads specs/features/message.yaml
3. Creates failing tests
4. Implements code
5. Runs quality checks
6. Reports completion
```

---

## Error Handling

### Scenario: core-development-expert Invoked Without Payload

```
core-development-expert:
"❌ CANNOT PROCEED WITHOUT APPROVED SPECIFICATIONS

I cannot start development without approved specifications from spec-planner.

Required workflow:
1. spec-planner creates specifications → gets user approval
2. spec-planner hands over to core-development-expert (me)
3. I implement tests and code

Please use the spec-planner agent first to create specifications for this task."
```

### Scenario: Handover Payload Missing Required Fields

```
core-development-expert:
"❌ INVALID HANDOVER PAYLOAD

Missing required fields: spec_files, component_type

Please ensure spec-planner provides complete handover payload with:
- spec_files: [list of spec paths]
- component_type: feature|chain|core|machine
- test_directory: [path]
- implementation_files: [list of files]
- summary: [description]"
```

### Scenario: Spec Files Don't Exist

```
core-development-expert:
"❌ SPECIFICATION FILES NOT FOUND

Handover payload references:
- specs/features/message.yaml [NOT FOUND]

Please ensure spec-planner has written spec files before handover."
```