# AI Operation Methods Analysis - complete() vs capture() vs template()

**Date**: 2026-01-02
**Context**: Investigating why machine-agent uses `template()` instead of `complete()`/`capture()`, and identifying functional constraints preventing ModelPool usage.

---

## Executive Summary

**Critical Findings**:
1. ❌ **complete() and capture() DO NOT use ModelPool** - they only accept hardcoded backend strings
2. ❌ **ModelPool is injected but never called** - selectModel() exists but is unused
3. ❌ **All AI operations hardcode 'ollama' backend** - violating complexity-based model selection principle
4. ✅ **template() was chosen for streaming**, but also hardcodes backend
5. ⚠️ **complete() returns Generator, NOT string** - common misconception in helper code

**Required Action**: Implement ModelPool integration in complete()/capture() methods to enable complexity-based model selection.

---

## Method Comparison

### 1. $this->complete(string $prompt, string $backend): Generator

**Location**: `src/Feature/Ai/AiFeature.php:181-197`

**Implementation**:
```php
if ($params->name === 'complete') {
    $args = $params->payload;
    $prompt = array_shift($args);
    $backend = array_shift($args) ?? 'openai';  // ← Hardcoded default

    $backendInstance = $this->resolveBackend($backend, $backends);

    $request = new RequestBuilder()
        ->setPrompt($prompt)
        ->build();

    $completion = new Completion($request, true, $backendInstance);
    return $completion();  // ← Returns Generator
}
```

**Characteristics**:
- **Returns**: Generator (yields string chunks)
- **Backend Selection**: Hardcoded string ('ollama', 'openai', 'anthropic')
- **ModelPool Usage**: ❌ NONE - $modelPool parameter exists but is never used
- **Streaming**: Yes - yields chunks as they arrive
- **Use Case**: Text completion tasks

**Current Usage in machine-agent**:
```php
// In AiHelper.php:44-46
public function complete(string $prompt, string $backend = 'ollama'): string
{
    return $this->context->complete($prompt, $backend);  // ← Hardcoded 'ollama'
}
```

**Problem**: AiHelper assumes complete() returns string, but it returns Generator!

---

### 2. $this->capture(string $prompt, array $schema, string $backend): array

**Location**: `src/Feature/Ai/AiFeature.php:147-178`

**Implementation**:
```php
if ($params->name === 'capture') {
    $args = $params->payload;
    $prompt = array_shift($args);
    $schema = array_shift($args);
    $backend = array_shift($args) ?? 'openai';  // ← Hardcoded default

    $backendInstance = $this->resolveBackend($backend, $backends);

    $resolvedSchema = $this->resolveSchema($schema, []);

    $request = new RequestBuilder()
        ->setPrompt($prompt)
        ->setResponseFormat(
            new ResponseFormat('json_schema', [
                'name' => 'result',
                'schema' => $resolvedSchema,
            ])
        )
        ->build();

    // Execute and consume generator
    $generator = new Chat($request, true, $backendInstance)();
    $result = implode(iterator_to_array($generator, false));
    $decoded = json_decode($result, true);

    return $decoded;  // ← Returns array directly
}
```

**Characteristics**:
- **Returns**: array (JSON decoded)
- **Backend Selection**: Hardcoded string
- **ModelPool Usage**: ❌ NONE
- **Streaming**: Internal only (consumed before return)
- **Use Case**: Structured data extraction with JSON schema validation

**Current Usage**:
```php
// In AiHelper.php:109 (analyzeRequirements)
return $this->captureJson($prompt, $schema, 'ollama');  // ← Hardcoded 'ollama'

// In holon.yml:300
$plan = $this->capture($prompt, $planSchema, 'ollama');  // ← Hardcoded 'ollama'
```

---

### 3. $this->template(string $template): Generator

**Location**: `src/Feature/Template/TemplateFeature.php` (registered by AiFeature)

**Usage Pattern**:
```php
$template = $this->template(
    '{{#complete max=4000 backend="ollama"}}{{promptText}}{{/complete}}'
);

// Consume generator
while ($template->valid()) {
    $chunk = $template->current();
    $yaml .= $chunk;
    $template->next();
    yield;
}
```

**Template Helper Implementation** (`src/Feature/Ai/AiFeature.php:207-287`):
```php
$helpers->registerHelper(
    'complete',
    function (Invocation $invocation, callable $next) use ($backends, $promptTemplate) {
        $backendName = $invocation->hash['backend'] ?? 'openai';  // ← Hardcoded default
        $backend = $backends[$backendName] ?? $backends['openai'];

        $request = new RequestBuilder();
        if (isset($invocation->hash['max'])) {
            $request->setMaxTokens((int)$invocation->hash['max']);
        }

        // ... build prompt with template ...

        yield from new Completion($request->build(), true, $backend)();
    }
);
```

**Characteristics**:
- **Returns**: Generator (yields string chunks via Mustache template)
- **Backend Selection**: Hardcoded in template string
- **ModelPool Usage**: ❌ NONE
- **Streaming**: Yes - manual chunk collection required
- **Use Case**: Text generation with template interpolation

**Current Usage in machine-agent**:
```php
// In holon.yml:360
$template = $this->template('{{#complete max=4000 backend="ollama"}}{{promptText}}{{/complete}}');

// In TemplateHelper.php:47-48
$template = $this->context->template(
    "{{#complete max={$maxTokens} backend=\"{$backend}\"}}{{_tpl_prompt}}{{/complete}}"
);
```

**Problem**: Backend is hardcoded in template string, no way to use ModelPool.

---

## Why template() Was Chosen Over complete()

### Original Reasoning (Inferred)

1. **Streaming Capability**: template() yields chunks, allowing progressive output
2. **MaxTokens Control**: Template helpers accept `max` parameter for token limits
3. **Template Interpolation**: Can embed context variables in prompt
4. **Manual Control**: Developer explicitly consumes generator, yielding for async scheduler

### Actual Problem

The choice wasn't based on functionality but on **lack of ModelPool integration**:

- `complete()` WOULD work, but returns Generator (not string as helpers assume)
- `capture()` DOES work for structured data (correctly used in machine-agent)
- `template()` was chosen for YAML/PHP generation because it's the only way to get streaming with maxTokens

**Root Issue**: None of the methods integrate with ModelPool, so hardcoded backends are used everywhere.

---

## ModelPool Integration - Current State

### ModelPool Configuration (machine-generator/holon.yml)

```yaml
- class: Noem\State\Feature\Ai\AiConfigFeature
  config:
    modelPool:
      - id: 'qwen-coder'
        provider: 'ollama'
        model: 'qwen2.5-coder:14b-instruct-q4_K_M'
        complexity: 3
        context: 32
        cost: 'low'
    preferences:
      defaultComplexity: 2
      defaultContext: 32
      preferProviders: ['ollama']
```

### ModelPool.selectModel() API

```php
public function selectModel(?int $complexity = null, ?int $context = null): ?array
{
    $reqComplexity = $complexity ?? $this->defaultComplexity;
    $reqContext = $context ?? $this->defaultContext;

    $candidates = [];

    foreach ($this->models as $model) {
        if ($model['complexity'] >= $reqComplexity && $model['context'] >= $reqContext) {
            $candidates[] = $model;
        }
    }

    // Score by proximity to requirements
    usort($candidates, function ($a, $b) use ($reqComplexity, $reqContext) {
        $scoreA = abs($a['complexity'] - $reqComplexity) * 10 + abs($a['context'] - $reqContext);
        $scoreB = abs($b['complexity'] - $reqComplexity) * 10 + abs($b['context'] - $reqContext);
        return $scoreA <=> $scoreB;
    });

    return $candidates[0];  // Returns: ['id' => ..., 'provider' => ..., 'model' => ...]
}
```

### Current Integration Status

**AiFeature BoundAccess Registration** (`AiFeature.php:128`):
```php
function (
    ?BoundAccess $boundAccess,
    Mesh $backends,
    PromptTemplate $promptTemplate,
    ?ModelPool $modelPool = null  // ← Injected but NEVER USED
): void {
```

**Problem**: ModelPool is available in closure but never called. Both complete() and capture() ignore it completely.

---

## Functional Constraints Preventing ModelPool Usage

### Constraint 1: Signature Mismatch

**Current Signatures**:
```php
$this->complete(string $prompt, string $backend): Generator
$this->capture(string $prompt, array $schema, string $backend): array
```

**Backend parameter is a STRING** - expects 'ollama', 'openai', 'anthropic'.

**ModelPool Returns**:
```php
[
    'id' => 'qwen-coder',
    'provider' => 'ollama',       // ← This is what we need
    'model' => 'qwen2.5-coder:...',
    'complexity' => 3,
    'context' => 32,
    'cost' => 'low'
]
```

**Gap**: Methods need to accept complexity/context parameters AND use ModelPool to resolve backend.

---

### Constraint 2: Template Helper Backend Hardcoding

**Template Helper Pattern**:
```php
{{#complete max=4000 backend="ollama"}}...{{/complete}}
```

**Problem**: Backend is a literal string in template, no way to pass complexity/context.

**Required Enhancement**:
```php
{{#complete max=4000 complexity=3 context=32}}...{{/complete}}
```

Then template helper needs to:
1. Check if `complexity` is provided
2. Call ModelPool.selectModel(complexity, context)
3. Use returned provider for backend selection

---

### Constraint 3: Helper Wrapper Assumptions

**AiHelper.complete()** (`machines/machine-agent/src/AiHelper.php:44-46`):
```php
public function complete(string $prompt, string $backend = 'ollama'): string
{
    return $this->context->complete($prompt, $backend);  // ← Assumes returns string
}
```

**Actual Return**: Generator!

**Fix Required**:
```php
public function complete(string $prompt, string $backend = 'ollama'): string
{
    $generator = $this->context->complete($prompt, $backend);
    $result = '';
    foreach ($generator as $chunk) {
        $result .= $chunk;
    }
    return $result;
}
```

But better: Accept complexity parameter and use ModelPool.

---

## Proposed Solution

### Option 1: Enhanced Signatures (Recommended)

**New API**:
```php
// New overloads that accept complexity/context
$this->complete(
    string $prompt,
    ?int $complexity = null,    // New parameter
    ?int $context = null,       // New parameter
    ?string $backend = null     // Falls back to ModelPool if null
): Generator

$this->capture(
    string $prompt,
    array $schema,
    ?int $complexity = null,    // New parameter
    ?int $context = null,       // New parameter
    ?string $backend = null     // Falls back to ModelPool if null
): array
```

**Implementation** (`AiFeature.php`):
```php
if ($params->name === 'complete') {
    $args = $params->payload;
    $prompt = array_shift($args);
    $complexity = array_shift($args);  // Can be null
    $context = array_shift($args);     // Can be null
    $backend = array_shift($args);     // Can be null

    // Use ModelPool if no explicit backend
    if ($backend === null && $modelPool !== null) {
        $selected = $modelPool->selectModel($complexity, $context);
        if ($selected !== null) {
            $backend = $selected['provider'];  // 'ollama', 'anthropic', etc.
        }
    }

    $backend = $backend ?? 'openai';  // Final fallback
    $backendInstance = $this->resolveBackend($backend, $backends);

    // ... rest of implementation
}
```

**Usage**:
```php
// Explicit complexity/context (uses ModelPool)
$text = $this->complete('Generate greeting', 3, 32);

// Fallback to defaults (uses ModelPool with default complexity)
$text = $this->complete('Generate greeting');

// Override with specific backend (bypasses ModelPool)
$text = $this->complete('Generate greeting', null, null, 'anthropic');
```

---

### Option 2: Template Helper Enhancement

**New Template Syntax**:
```php
{{#complete max=4000 complexity=3 context=32}}
Generate YAML for todo list machine
{{/complete}}
```

**Implementation** (`AiFeature.php template helper`):
```php
$helpers->registerHelper(
    'complete',
    function (Invocation $invocation, callable $next) use ($backends, $promptTemplate, $modelPool) {
        // Check for complexity-based selection
        $complexity = $invocation->hash['complexity'] ?? null;
        $contextSize = $invocation->hash['context'] ?? null;
        $backendName = $invocation->hash['backend'] ?? null;

        // Use ModelPool if complexity provided and no explicit backend
        if ($backendName === null && $modelPool !== null && $complexity !== null) {
            $selected = $modelPool->selectModel((int)$complexity, (int)$contextSize);
            if ($selected !== null) {
                $backendName = $selected['provider'];
            }
        }

        $backendName = $backendName ?? 'openai';
        $backend = $backends[$backendName] ?? $backends['openai'];

        // ... rest of implementation
    }
);
```

---

### Option 3: Hybrid Approach (Most Flexible)

Implement BOTH:
1. Enhanced complete()/capture() signatures for programmatic use
2. Template helper complexity parameters for template-based generation

This allows:
```php
// In PHP callbacks - programmatic
$analysis = $this->capture($prompt, $schema, 3, 32);  // Complexity-based

// In templates - declarative
$this->template('{{#complete complexity=3 context=32}}{{prompt}}{{/complete}}');
```

---

## Recommended Implementation Plan

### Phase 1: Core Method Enhancement

1. **Update complete() BoundAccess handler**:
   - Add complexity/context parameters
   - Implement ModelPool.selectModel() call
   - Fall back to explicit backend if provided
   - Fall back to 'openai' as final default

2. **Update capture() BoundAccess handler**:
   - Same parameter additions
   - Same ModelPool integration

3. **Update helper wrappers**:
   - AiHelper.complete() - consume generator properly
   - AiHelper.capture() - pass complexity/context
   - TemplateHelper - add complexity parameters

### Phase 2: Template Helper Enhancement

1. **Update {{#complete}} helper**:
   - Add complexity/context hash parameters
   - Integrate ModelPool selection
   - Maintain backward compatibility with backend parameter

2. **Update {{#capture}} helper**:
   - Same enhancements

### Phase 3: Holon Migration

1. **Remove hardcoded 'ollama' backends**:
   - Update analyzeRequirements() to pass complexity
   - Update generateQuestion() to use defaults
   - Update createPlan() to pass complexity based on task

2. **Update YAML generation**:
   - Template helpers use complexity instead of backend
   - Generated machines inherit parent ModelPool config

---

## Breaking Changes Assessment

### Backward Compatibility

**Current Signatures Still Work**:
```php
// Old style (still works, ignores ModelPool)
$this->complete($prompt, 'ollama')
$this->capture($prompt, $schema, 'ollama')
```

**New Style (opt-in)**:
```php
// New style (uses ModelPool)
$this->complete($prompt, 3, 32)
$this->capture($prompt, $schema, 3, 32)

// Mixed (complexity with backend override)
$this->complete($prompt, 3, 32, 'anthropic')
```

**Parameter Interpretation**:
- If arg2 is string → old style (backend)
- If arg2 is int → new style (complexity)

Alternatively, use named parameters (PHP 8):
```php
$this->complete($prompt, complexity: 3, context: 32)
```

---

## Example: analyzeRequirements() Refactored

**Before (Hardcoded 'ollama')**:
```php
public function analyzeRequirements(string $userRequest, array $qaHistory = []): array
{
    $schema = [ /* ... */ ];
    $prompt = "Analyze this state machine request:\n\n{$context}";

    return $this->captureJson($prompt, $schema, 'ollama');  // ← Hardcoded
}
```

**After (Complexity-Based)**:
```php
public function analyzeRequirements(
    string $userRequest,
    array $qaHistory = [],
    int $complexity = 2  // Analysis task = moderate complexity
): array
{
    $schema = [ /* ... */ ];
    $prompt = "Analyze this state machine request:\n\n{$context}";

    // Uses ModelPool to select appropriate model for complexity level
    return $this->captureJson($prompt, $schema, $complexity, context: 16);
}
```

**What Happens**:
1. ModelPool.selectModel(2, 16) is called
2. Returns model with complexity >= 2 and context >= 16
3. Uses that model's provider ('ollama', 'anthropic', etc.)
4. Automatically scales to more powerful models if needed

---

## Testing Strategy

### Unit Tests

1. **Test ModelPool Selection**:
   - Verify selectModel() returns correct model for complexity levels
   - Test fallback when no model matches requirements
   - Test default complexity/context

2. **Test complete() with ModelPool**:
   - Mock ModelPool returning different providers
   - Verify correct backend is selected
   - Test backward compatibility with string backend

3. **Test capture() with ModelPool**:
   - Same as complete() tests
   - Verify JSON schema validation still works

### Integration Tests

1. **Test machine-agent with ModelPool**:
   - Configure pool with multiple models
   - Verify different complexity tasks use different models
   - Test graceful degradation when model unavailable

2. **Test template helpers**:
   - Verify {{#complete complexity=3}} uses ModelPool
   - Verify {{#complete backend="ollama"}} bypasses ModelPool
   - Test hybrid scenarios

---

## Conclusion

### Critical Findings

1. **ModelPool is completely unused** - Despite being injected into AiFeature, it's never called
2. **All operations hardcode 'ollama'** - Violates the complexity-based model selection principle
3. **complete() returns Generator** - Helper wrappers incorrectly assume it returns string
4. **template() was chosen for streaming** - But also hardcodes backend, defeating ModelPool purpose

### Required Actions (Priority Order)

1. **HIGH**: Implement ModelPool integration in complete()/capture() BoundAccess handlers
2. **HIGH**: Update template helpers to support complexity/context parameters
3. **MEDIUM**: Refactor AiHelper/TemplateHelper to use complexity instead of hardcoded backends
4. **MEDIUM**: Fix AiHelper.complete() to properly consume Generator
5. **LOW**: Update machine-agent holon.yml to use complexity-based selection

### Estimated Impact

- **Code Changes**: ~200 lines in AiFeature.php, ~50 lines in helpers
- **Migration Effort**: Update 15-20 call sites in machine-agent
- **Breaking Changes**: None (backward compatible with parameter overloading)
- **Performance**: Negligible (ModelPool.selectModel() is O(n) where n = model count, typically < 10)

---

**Next Steps**:
1. Review findings with project owner
2. Get approval for implementation approach
3. Create specification for ModelPool integration
4. Implement Phase 1 (core method enhancement)
5. Test and iterate

---

**Document Version**: 1.0
**Last Updated**: 2026-01-02
**Author**: Claude (Code Analysis)
