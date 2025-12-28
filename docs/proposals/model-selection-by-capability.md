# Model Selection by Capability

## Status
**Draft** - Proposed

## Summary
Enable declarative model selection based on abstract capability requirements (complexity, context) rather than specific model names, with transparent sourcing from a configurable cross-provider model pool.

## Motivation

### Current State
Model selection requires explicit knowledge of specific models:
```php
// Current approach - tightly coupled to specific models
$this->capture($prompt, $schema, backend: 'anthropic');
$this->template('{{#complete backend="ollama"}}...');
```

**Problems:**
1. **Tight Coupling**: Code explicitly references provider/model names
2. **No Abstraction**: Caller must know which model fits their needs
3. **Provider Lock-in**: Switching providers requires code changes
4. **No Cost Optimization**: Can't automatically use cheaper models for simple tasks
5. **Manual Balancing**: Can't distribute load across providers

### Desired State
Select models by capability requirements:
```php
// Proposed - declarative capability-based selection
$this->capture($prompt, $schema, complexity: 2, context: 10);
$this->template('{{#complete complexity=1 context=5}}...');
```

**Benefits:**
1. **Abstraction**: Caller specifies "what I need" not "which model"
2. **Flexibility**: Same code works across different model pools
3. **Cost Optimization**: Use simple/cheap models when appropriate
4. **Load Balancing**: Distribute across providers automatically
5. **Future-Proof**: New models added via config, not code changes

## Proposal

### Core Concept

**Model Capability Dimensions:**

1. **Complexity** (0-5 scale)
   - `0`: Basic pattern matching, simple classification
   - `1`: Simple reasoning, structured output
   - `2`: Moderate reasoning, multi-step logic
   - `3`: Complex reasoning, nuanced understanding
   - `4`: Advanced reasoning, creative problem-solving
   - `5`: Frontier reasoning, research-level tasks

2. **Context** (logarithmic scale, in K tokens)
   - `1`: 1K tokens (~750 words)
   - `2`: 2K tokens
   - `4`: 4K tokens
   - `8`: 8K tokens
   - `16`: 16K tokens
   - `32`: 32K tokens
   - `64`: 64K tokens
   - `128`: 128K+ tokens

**Selection Logic:**
- Find models matching or exceeding both requirements
- Prefer closest match to avoid waste
- Fall back to higher capabilities if exact match unavailable
- Support provider preferences and exclusions

### API Design

#### Context Method Signature
```php
// ExtendedState context methods
$result = $this->capture(
    prompt: string,
    schema: ?array = null,
    complexity: ?int = null,
    context: ?int = null,
    backend: ?string = null,  // Optional override
    prefer: ?array = null     // ['anthropic', 'openai'] - provider preferences
);

$result = $this->complete(
    prompt: string,
    complexity: ?int = null,
    context: ?int = null,
    backend: ?string = null,
    prefer: ?array = null
);
```

#### Template Helper Syntax
```mustache
{{#capture "variable" complexity=2 context=10}}
Extract user intent from: {{userInput}}
{{/capture}}

{{#complete complexity=3 context=16 prefer="anthropic,openai"}}
Generate detailed analysis of: {{document}}
{{/complete}}
```

#### Configuration Format (YAML)
```yaml
# AiConfigFeature with model pool definition
features:
  - class: Noem\State\Feature\Ai\AiConfigFeature
    config:
      modelPool:
        # Define available models with capabilities
        - id: gpt-4-turbo
          provider: openai
          model: gpt-4-turbo-preview
          complexity: 4
          context: 128
          cost: high

        - id: claude-sonnet-3.5
          provider: anthropic
          model: claude-3-5-sonnet-20241022
          complexity: 4
          context: 200
          cost: medium

        - id: gpt-3.5-turbo
          provider: openai
          model: gpt-3.5-turbo
          complexity: 2
          context: 16
          cost: low

        - id: llama-3-70b
          provider: ollama
          model: llama3:70b
          complexity: 3
          context: 8
          cost: free

        - id: ministral-3b
          provider: ollama
          model: ministral-3:3b
          complexity: 1
          context: 32
          cost: free

      # Selection preferences
      preferences:
        defaultComplexity: 2
        defaultContext: 8
        preferProviders: [ollama, openai, anthropic]
        costBias: moderate  # 'low' | 'moderate' | 'high' | 'none'

      # Provider credentials
      credentials:
        openai:
          apiKey: ${OPENAI_API_KEY}
        anthropic:
          apiKey: ${ANTHROPIC_API_KEY}
        ollama:
          baseUrl: http://localhost:11434/api
```

### Selection Algorithm

```
function selectModel(complexity, context, preferences):
    1. Filter models by minimum requirements:
       - model.complexity >= requested.complexity
       - model.context >= requested.context

    2. Apply provider preferences:
       - Sort by preferred provider order
       - Exclude blacklisted providers

    3. Apply cost bias:
       - low: Prefer free/cheap models
       - moderate: Balance cost and capability
       - high: Prefer best capability regardless of cost
       - none: Don't consider cost

    4. Score remaining models:
       score =
         - abs(model.complexity - requested.complexity) * 10
         - abs(model.context - requested.context) * 1
         + providerPreferenceBonus
         + costBiasAdjustment

    5. Return model with lowest score (closest match)
```

### Implementation Plan

#### Phase 1: Core Infrastructure
**Files to modify:**
- `src/Feature/Ai/AiConfigFeature.php`
  - Add model pool configuration parsing
  - Implement model selection algorithm
  - Create `ModelPool` class

**New files:**
- `src/Feature/Ai/ModelPool.php`
  - Model capability metadata
  - Selection algorithm
  - Provider management

- `src/Feature/Ai/ModelCapability.php`
  - Value object for model capabilities
  - Complexity/context scales

#### Phase 2: API Integration
**Files to modify:**
- `src/Feature/Ai/AiFeature.php`
  - Update `capture()` context method signature
  - Add capability-based backend resolution
  - Update template helper signatures

**Acceptance Criteria:**
```yaml
specs:
  - acceptanceCriteria: Capture accepts complexity and context parameters
    criticality: contract

  - acceptanceCriteria: ModelPool selects model matching complexity requirement
    criticality: contract

  - acceptanceCriteria: ModelPool selects model matching context requirement
    criticality: contract

  - acceptanceCriteria: ModelPool prefers exact match over higher capability
    criticality: behavior

  - acceptanceCriteria: ModelPool respects provider preference order
    criticality: behavior

  - acceptanceCriteria: ModelPool falls back to higher capability if no match
    criticality: behavior

  - acceptanceCriteria: Backend parameter overrides capability-based selection
    criticality: contract

  - acceptanceCriteria: Template helpers support complexity and context params
    criticality: contract
```

#### Phase 3: Advanced Features
- Cost tracking and reporting
- Load balancing across providers
- Automatic failover on provider errors
- Model performance metrics collection
- A/B testing support

## Examples

### Example 1: Simple Classification
```php
// Need minimal reasoning, small context
$classification = $this->capture(
    prompt: "Classify: {$userInput}",
    schema: $classificationSchema,
    complexity: 1,  // Simple task
    context: 4      // Small input
);
// → Selects: ministral-3:3b (Ollama, free)
```

### Example 2: Complex Analysis
```php
// Need advanced reasoning, large context
$analysis = $this->capture(
    prompt: "Analyze requirements: {$longDocument}",
    schema: $analysisSchema,
    complexity: 4,  // Complex reasoning
    context: 64     // Large document
);
// → Selects: claude-3-5-sonnet (Anthropic, best match)
```

### Example 3: Provider Preference
```php
// Prefer local models for privacy
$summary = $this->complete(
    prompt: "Summarize: {$sensitiveData}",
    complexity: 2,
    context: 16,
    prefer: ['ollama']
);
// → Selects: llama3:70b (Ollama, local)
```

### Example 4: Cost Optimization
```yaml
# Config with cost bias
preferences:
  costBias: low  # Prefer cheap models

# Usage (same code)
$result = $this->capture($prompt, $schema, complexity: 2, context: 8);
# → Selects cheapest model meeting requirements
```

### Example 5: Template Usage
```mustache
{{#capture "taskStructure" complexity=3 context=16}}
Generate state machine for: {{userRequest}}

Requirements:
- Must be valid YAML
- Include all transition guards
- Handle error states
{{/capture}}
```

## Migration Strategy

### Backward Compatibility
**All existing code continues to work:**
```php
// Old: explicit backend selection (still supported)
$this->capture($prompt, $schema, backend: 'ollama');

// New: capability-based selection (opt-in)
$this->capture($prompt, $schema, complexity: 2, context: 10);
```

**Migration Path:**
1. Add AiConfigFeature with model pool to existing configs
2. Gradually replace `backend:` parameters with `complexity:` + `context:`
3. Old code continues to work unchanged

### Default Behavior
**Without configuration:**
- Falls back to current behavior (use named backends)
- No breaking changes

**With AiConfigFeature but no capability params:**
- Uses `defaultComplexity` and `defaultContext` from config
- Transparent upgrade path

## Trade-offs

### Pros
✅ **Abstraction**: Decouple from specific models
✅ **Flexibility**: Easy provider switching
✅ **Cost Optimization**: Auto-select cheap models for simple tasks
✅ **Future-Proof**: New models via config, not code
✅ **Load Balancing**: Distribute across providers

### Cons
❌ **Complexity**: More configuration required
❌ **Learning Curve**: Users must understand capability scales
❌ **Indirection**: Harder to debug which model was selected
❌ **Overhead**: Selection algorithm adds minimal latency

### Mitigations
- **Logging**: Log selected model for each request
- **Defaults**: Sensible defaults for common use cases
- **Documentation**: Clear capability scale explanations
- **Debugging**: Add debug mode showing selection reasoning

## Open Questions

1. **Capability Calibration**: How do we maintain accurate complexity/context ratings as models evolve?
   - Proposed: Periodic review, user feedback, automated benchmarking

2. **Model Availability**: How to handle models that go offline?
   - Proposed: Health checks, automatic exclusion, failover

3. **Streaming Support**: How do capabilities affect streaming behavior?
   - Proposed: Maintain current streaming logic, selection happens before streaming

4. **Cost Tracking**: Should we track actual API costs?
   - Proposed: Phase 3 feature, optional cost monitoring

5. **Provider Rate Limits**: How to handle rate limiting?
   - Proposed: Provider-level backoff, automatic failover to alternate provider

## Related Work

- **LangChain Model Selection**: Uses model names, not capabilities
- **OpenAI Model Routing**: Automatic but opaque, no control
- **Anthropic Model Family**: Named tiers (haiku/sonnet/opus) but provider-specific

**Our Approach Differs:**
- Provider-agnostic capability dimensions
- Declarative configuration
- Transparent selection logic

## Success Metrics

- **Adoption**: % of capture/complete calls using capabilities vs backends
- **Cost Reduction**: Actual API costs before/after capability-based selection
- **Provider Distribution**: Balance across configured providers
- **Code Changes**: Reduced changes needed when adding new models

## Future Enhancements

1. **Dynamic Capability Adjustment**: Learn from usage patterns
2. **Model Benchmarking**: Automated capability validation
3. **Cost Budgets**: Per-feature or per-machine cost limits
4. **Quality Feedback**: Track output quality, adjust selections
5. **Multi-Model Strategies**: Ensemble, validation, consensus

## References

- [OpenAI Models Documentation](https://platform.openai.com/docs/models)
- [Anthropic Model Comparison](https://docs.anthropic.com/claude/docs/models-overview)
- [Ollama Model Library](https://ollama.ai/library)

---

**Author**: AI Development Team
**Date**: 2025-12-22
**Version**: 1.0.0
