# Requirement Analysis Improvements

## Problem

The machine-agent was accepting vague requests (e.g., "a wedding planner") and jumping straight to implementation without asking clarifying questions, even though critical implementation details were missing.

## Root Cause

The AI analysis prompt in `AiHelper::analyzeRequirements()` was too lenient:
- It assessed **conceptual understanding** rather than **implementation readiness**
- No clear guidance on confidence scoring
- No examples of what constitutes sufficient detail
- Generic prompt led to inflated confidence scores

## Solution

### 1. Stricter Analysis Criteria (`machines/machine-agent/src/AiHelper.php:108-143`)

**New Prompt Structure:**
```
CRITICAL ASSESSMENT CRITERIA:
You are assessing whether we have SUFFICIENT IMPLEMENTATION DETAILS,
not just conceptual understanding.

For state machines, we need:
- Clear workflow/state transitions
- Specific features/capabilities
- Data requirements
- Trigger conditions
- Actions to perform
```

**Confidence Scoring Guide:**
- 0.9-1.0: Complete implementation details (states, transitions, data, features explicit)
- 0.7-0.8: Good overview but missing some implementation details
- 0.5-0.6: Concept clear but most implementation details missing
- 0.3-0.4: Vague request, very few implementation details
- 0.0-0.2: Unclear or nonsensical request

**Examples Provided:**
```
❌ Low confidence (0.3): "a wedding planner"
   - Concept clear, but no states, data, or workflow specified

✅ High confidence (0.8): "task manager: idle->creating->active->completed states,
   store tasks with priority/title/description, validate non-empty titles"
```

### 2. Structured Interactions (`machines/machine-agent/src/AiHelper.php:148-226`)

The AI now chooses the **most appropriate interaction type** for each question:

**`select`** - Single choice from options
```json
{
  "interaction_type": "select",
  "question": "What type of workflow best describes your machine?",
  "options": {
    "linear": "Linear sequence (step 1 -> step 2 -> step 3)",
    "branching": "Branching with conditions (if/else paths)",
    "event-driven": "Event-driven (reacts to external triggers)"
  }
}
```

**`choice`** - Multiple selection
```json
{
  "interaction_type": "choice",
  "question": "Which features should this machine include? (Select all)",
  "options": {
    "persistence": "Save/load state to file or database",
    "validation": "Validate inputs and data",
    "async": "Asynchronous/background operations"
  }
}
```

**`prompt`** - Free-form text
```json
{
  "interaction_type": "prompt",
  "question": "Describe the main states and transitions (e.g., 'idle -> processing -> complete')"
}
```

### 3. Dynamic Interaction Dispatch (`machines/machine-agent/holon.yml:201-252`)

The questioning state now:
1. Receives structured question data from AI
2. Dispatches to appropriate interaction type (select/choice/prompt)
3. Renders proper UI through InteractionAdapter automatically
4. Stores answers and re-analyzes until confidence threshold met

## Expected Behavior

### Before (Vague Request)
```
User: "a wedding planner"
Agent: [Planning machine architecture...] ← No questions asked!
```

### After (Vague Request)
```
User: "a wedding planner"
Agent: Analyzing requirements...
Agent: What type of workflow best describes your machine?
  [ ] Linear sequence (step 1 -> step 2 -> step 3)
  [*] Event-driven (reacts to external triggers)
  [ ] Repeating loop (continuously processes items)
Select option (0-2): 1

Agent: Which features should this machine include? (Select all)
  [ ] [0] Save/load state to file or database
  [*] [1] Validate inputs and data
  [*] [2] Message passing between states
Select options (comma-separated): 1,2

Agent: Describe the main states and workflow for your wedding planner...
> idle -> vendor_selection -> guest_management -> timeline_planning -> complete

Agent: [Planning machine architecture with collected details...]
```

## Configuration

**Confidence Threshold:** `0.75` (set in `machines/machine-agent/holon.yml:109`)
- Can be lowered to `0.70` for more questions
- Can be raised to `0.80` for fewer questions

**Max Question Iterations:** Loops between `analyzing_requirements` ↔ `questioning` until confidence >= threshold or user cancels

## Benefits

✅ **Better Requirements:** Forces specification of implementation details upfront
✅ **User Guidance:** Structured options help users understand what's needed
✅ **Reduced Ambiguity:** Multiple-choice reduces interpretation errors
✅ **Better AI Output:** More detailed requirements → better generated machines
✅ **Generic Pattern:** Same approach works for ANY sub-agent using InteractionFeature
✅ **High-Level Questions:** Asks about deliverables and purpose, not technical implementation
✅ **Progress Feedback:** Shows confidence score and summary after each question

## Testing

Test with increasingly vague requests:

```bash
# Should ask 0-1 questions (detailed)
echo "Task manager with todo/in-progress/done states, store priority and title" | ddev exec php run.php machines/cli-agent/holon.yml

# Should ask 2-3 questions (moderate)
echo "A todo list manager" | ddev exec php run.php machines/cli-agent/holon.yml

# Should ask 3-5 questions (vague)
echo "A wedding planner" | ddev exec php run.php machines/cli-agent/holon.yml
```

## Architecture

This implementation demonstrates the **power of the generic interaction system**:

1. **Sub-agent** (machine-agent) decides WHAT to ask and HOW (select/choice/prompt)
2. **InteractionAdapter** handles rendering the UI dynamically
3. **No hardcoded logic** - works with ANY sub-agent using InteractionFeature
4. **Framework-agnostic** - sub-agent doesn't know about CLI

This is exactly the kind of dynamic, capability-driven communication you wanted!

## Recent Improvements (2026-01-10)

### 1. Better Error Handling (`machines/machine-agent/src/AiHelper.php:33-65`)
- Added comprehensive null checks and error messages for `captureJson()`
- Provides clear diagnostics when AI backend fails or returns invalid data
- Prevents cryptic "Return value must be of type array, null returned" errors

### 2. High-Level Question Generation (`machines/machine-agent/src/AiHelper.php:215-285`)
- Question priority guidance based on question count (0-based)
- **First question:** Always asks about DELIVERABLE TYPE (CLI tool, web app, API, etc.)
- **Second question:** Asks about PURPOSE and core functionality
- **Third question:** Asks about user-facing operations/capabilities
- **Later questions:** Implementation details and specific features
- Explicitly instructs AI to AVOID technical concepts like "state transitions" or "workflow patterns"

### 3. Progress Updates (`machines/machine-agent/holon.yml:251-270`)
- After each question, displays:
  - Current confidence score (percentage)
  - Number of questions asked
  - Brief summary of what's been gathered so far
- Helps users understand progress toward implementation readiness

### 4. Null-Safe Question Count (`machines/machine-agent/holon.yml:202`)
- Defensive coding: `$questionCount = $this->get('question_count', 0) ?? 0`
- Prevents type errors when ExtendedState returns null

### Examples

**Before (too technical):**
```
Question: "Which core pattern should guide this workflow's state management?"
Options: "A defined sequence of states (pending → confirmed → paid)"
```

**After (high-level, user-focused):**
```
Question: "What kind of system are you building for wedding planning?"
Options:
- "A tool primarily designed for the wedding planner to organize tasks"
- "A system where the planner and client interact directly (web app)"
- "A collaborative platform integrating planner, client, and vendors"
```
