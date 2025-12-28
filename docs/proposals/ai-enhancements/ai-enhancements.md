# AI Feature Multi-Backend Enhancement Proposal

**Status**: Draft
**Author**: Claude Code
**Date**: 2025-12-13
**Updated**: 2025-12-20
**Related Components**: AiFeature, Completion, Chat, RequestBuilder, Mesh (backends), PromptTemplate

## Overview

This proposal outlines enhancements to the AI Feature to support multiple backend providers (OpenAI, Ollama, Anthropic, etc.) and model-specific message templates. The current implementation is hardcoded for a single backend with limited flexibility for different AI providers.

**Critical Focus**: This proposal now includes comprehensive integration with the Region infrastructure, including Feature system, ChainMail dependency injection, ExtendedState context, AsyncFeature cooperation, and spec-driven development methodology.

## Current Architecture Analysis

The current AI Feature consists of:
- `AiFeature`: Main feature class with template helpers
- `Completion/Chat`: API clients using hardcoded backend logic
- `RequestBuilder`: Request construction with hardcoded defaults
- `Request`: Immutable request DTO
- `ResponseFormat`: Structured output configuration

### Current Limitations
1. **Single Backend**: Hardcoded to work with one API endpoint/token
2. **Hardcoded Model Selection**: Model defaults are baked into RequestBuilder
3. **No Model-Dependent Logic**: All models get identical prompt formatting
4. **Limited Provider Support**: No abstraction for different API structures
5. **Inflexible Configuration**: No yaml/ini-based backend configuration
6. **No Region Integration**: Unclear how backends integrate with ChainMail, ExtendedState, and AsyncFeature
7. **No State Lifecycle Integration**: Missing strategy for backend selection per state

## Integration with Region Infrastructure

### Feature System Integration

The enhanced AiFeature must integrate with the Feature wrapper pattern and ChainMail dependency injection system.

**Current Implementation** (`src/Feature/Ai/AiFeature.php:15-17`):
```php
public function __invoke(ChainMail $chainMail): void
{
    $chainMail->supply(fn(): SystemPrompt => new SystemPrompt());
    $chainMail->use(function (Helpers $helpers): void { /* ... */ });
}
```

**Enhanced Implementation** (Mesh-based architecture):
```php
class AiFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        // 1. Supply backend mesh (array-like object backed by chains)
        $chainMail->supply(fn(): Mesh => new Mesh([
            'openai' => new OpenAiBackend(),
            'ollama' => new OllamaBackend(),
            'anthropic' => new AnthropicBackend(),
        ]));

        // 2. Supply prompt template resolution chain
        $chainMail->supply(fn(): PromptTemplate => new PromptTemplate());

        // 3. Enhanced SystemPrompt chain (no changes needed)
        $chainMail->supply(fn(): SystemPrompt => new SystemPrompt());

        // 4. Template helpers receive backend mesh and prompt template chain
        $chainMail->use(function (
            Helpers $helpers,
            Mesh $backends,
            PromptTemplate $promptTemplate
        ): void {
            $helpers->registerHelper('complete',
                $this->createCompleteHelper($backends, $promptTemplate)
            );
            $helpers->registerHelper('capture',
                $this->createCaptureHelper($backends, $promptTemplate)
            );
        });
    }
}
```

**Key Integration Points**:
- `Mesh $backends`: Array-like object containing backend instances (`$backends['openai']`)
- `PromptTemplate` chain: Provider-specific prompt formatting (model → provider → base)
- Backends keyed by name (not model detection patterns)
- Other features can extend mesh with additional backends via `$backends->extend()`
- Template helpers receive both mesh and prompt template chain
- No exposing getBackend() to state context

### Extending the Backend Mesh

Other features can add their own backends:

```php
class CustomAiFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use(function(Mesh $backends) {
            // Add custom backend to the mesh
            $backends['custom'] = new CustomBackend();

            // Or extend with chain-based logic
            $backends->extend(
                offsetGet: function($name, $next) {
                    if ($name === 'gemini') {
                        return new GeminiBackend();
                    }
                    return $next($name);
                }
            );
        });
    }
}
```

## Proposed Architecture (Mesh-Based)

### 1. Backend Mesh

Backends stored in a Mesh (array-like object backed by chains):

```php
// In AiFeature
$backends = new Mesh([
    'openai' => new OpenAiBackend(),
    'ollama' => new OllamaBackend(),
    'anthropic' => new AnthropicBackend(),
]);
```

**Access patterns**:
```php
// Template helper access
$backend = $backends[$backendName] ?? $backends['openai'];

// State action access (optionally pass backend name to Completion)
$completion = new Completion($prompt, 'ollama'); // backend name, not model
```

### 2. Backend Implementations

Concrete adapters for each provider:

```php
interface BackendInterface
{
    public function createCompletionRequest(Request $request): array;
    public function createChatRequest(Request $request): array;
    public function stream(Request $request): iterable;
    public function formatSystemPrompt(string $prompt): string;
}

class OpenAiBackend implements BackendInterface { /* ... */ }
class OllamaBackend implements BackendInterface { /* ... */ }
class AnthropicBackend implements BackendInterface { /* ... */ }
```

### 3. Prompt Template Resolution Chain

Provider-specific prompt formatting via chain (renamed from TemplateResolver):

```php
namespace Noem\State\Feature\Ai\Chains;

use Noem\State\Chains\Chain;

/**
 * Chain for resolving provider-specific prompt templates
 * Resolution order: model → provider → base
 */
class PromptTemplate extends Chain
{
    public function __invoke(string $backend, string $type): string
    {
        // Default resolution logic: model → provider → base
        $template = $this->templates[$backend][$type]
            ?? $this->getBaseTemplate($type);

        // Allow middleware to override
        return $this->next($backend, $type, $template);
    }

    private function getBaseTemplate(string $type): string
    {
        return $this->templates['base'][$type] ?? '';
    }
}
```

**Extensibility**:
```php
class CustomPromptFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use(function(PromptTemplate $promptTemplate) {
            return function(string $backend, string $type) use ($promptTemplate): string {
                // Custom template for code generation
                if ($type === 'code' && $backend === 'ollama') {
                    return $this->loadCustomCodeTemplate();
                }

                return $promptTemplate($backend, $type);
            };
        });
    }
}
```

### 4. Template Helpers (Enhanced)

Template helpers accept optional `backend=` parameter:

```php
$helpers->registerHelper('complete', function (Invocation $invocation, callable $next) use ($backends, $promptTemplate) {
    // Optional backend parameter: {{complete backend="ollama"}}
    $backendName = $invocation->hash['backend'] ?? 'openai';
    $backend = $backends[$backendName] ?? $backends['openai'];

    // Resolve provider-specific prompt template
    $template = $promptTemplate($backendName, 'completion');
    $prompt = $template . "\n\n" . $this->buildPrompt($invocation);

    $request = RequestBuilder::new()
        ->setPrompt($prompt)
        ->build();

    yield from new Completion($request, $backend)();
    yield from $next($invocation);
});
```

**Usage in templates**:
```handlebars
{{!-- Use default backend --}}
{{complete}}Write a function{{/complete}}

{{!-- Specify backend (uses backend-specific prompt template) --}}
{{complete backend="ollama"}}Generate code{{/complete}}
```

### 4. Credential Injection (Optional)

Credentials injected by extending the backend mesh:

```php
class AiConfigFeature implements Feature
{
    public function __construct(private array $credentials) {}

    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use(function(Mesh $backends) {
            // Inject credentials via mesh extension
            $backends->extend(
                offsetGet: function($name, $next) {
                    $backend = $next($name);
                    if ($backend && isset($this->credentials[$name])) {
                        $backend->setApiKey($this->credentials[$name]['apiKey'] ?? null);
                        $backend->setBaseUrl($this->credentials[$name]['baseUrl'] ?? null);
                    }
                    return $backend;
                }
            );
        });
    }
}
```

## Summary

**Mesh-Based Architecture**:
- Backends stored in `Mesh` (array-like, chain-backed)
- `PromptTemplate` chain for provider-specific prompt formatting
- Template helpers accept optional `backend=` parameter
- No custom events - states handle errors as needed
- No AI config in context state
- Full TemplateFeature integration
- Credential injection via optional `AiConfigFeature`

**Implementation Approach**:

1. **Backend Implementations**
   - Implement `BackendInterface` contract
   - Implement concrete backends (OpenAI, Ollama, Anthropic)
   - Each backend handles its own API specifics

2. **Backend Mesh**
   - Create mesh containing backend instances
   - Supply mesh via ChainMail
   - Test mesh extensibility

3. **PromptTemplate Chain**
   - Implement `PromptTemplate` chain for provider-specific formatting
   - Resolution order: model → provider → base
   - Supply via ChainMail
   - Test chain middleware wrapping

4. **Template Helper Enhancement**
   - Update `complete` helper to accept optional `backend=` parameter
   - Update `capture` helper to accept optional `backend=` parameter
   - Helpers receive mesh and PromptTemplate injection from ChainMail
   - Use PromptTemplate to format prompts per backend

5. **Optional Credential Injection**
   - Implement `AiConfigFeature` for credential injection
   - Extend mesh via `offsetGet` chain
   - Inject credentials when backends are accessed

6. **Integration Tests**
   - Test backend switching via `backend=` parameter
   - Test PromptTemplate chain extensibility
   - Test mesh extensibility (adding custom backends)
   - Test AsyncFeature cooperation


## Benefits

1. **Mesh-Based Extensibility**: Add backends via mesh extension (no rigid manager class)
2. **Provider-Specific Prompts**: PromptTemplate chain enables backend-specific prompt formatting
3. **Simple Backend Selection**: Template parameter `backend="ollama"` or pass name to Completion
4. **No Configuration Sprawl**: No AiConfiguration class - optional credential injection only
5. **Clean Separation**: Provider logic isolated in backend implementations
6. **TemplateFeature Integration**: Full utilization of existing template system
7. **Testability**: Both mesh and PromptTemplate chain easily testable and extensible

## Design Decisions

✅ **Mesh not Resolver**: Backends in Mesh (array-like, chain-backed), not resolution chain

✅ **PromptTemplate Chain**: Provider-specific prompt formatting via chain (renamed from TemplateResolver to avoid ambiguity with TemplateFeature)

✅ **Backend Names not Models**: Reference backends by name (`openai`, `ollama`), not model patterns

✅ **No Custom Events**: Don't create AiErrorEvent - states handle native exceptions

✅ **No AI Config in Context**: Don't store AI configuration in state context

✅ **Template Parameter**: `backend="name"` not `model="name"` in helpers

✅ **TemplateFeature Integration**: Use existing template system, don't create separate one

✅ **Optional Credentials**: AiConfigFeature extends mesh for credential injection (optional)

✅ **No getBackend() in Context**: Don't expose backend access method to states

## Next Steps (Spec-First Workflow)

**⚠️ CRITICAL**: All implementation must follow the spec-driven development workflow defined in CLAUDE.md.

### Step 1: Proposal Review & Approval
- [x] User reviews this enhanced proposal
- [x] Design decisions resolved
- [ ] User approves moving forward with spec planning

### Step 2: Specification Planning
- [ ] **Launch `spec-planner` agent** to enhance existing AI specification
- [ ] Agent updates `specs/features/ai.yaml` with:
  - Backend mesh infrastructure
  - Backend implementations (OpenAI, Ollama, Anthropic)
  - PromptTemplate chain (provider-specific prompt formatting)
  - Enhanced template helpers (`backend=` parameter)
  - Optional credential injection feature
  - Mesh extensibility patterns
- [ ] **User reviews and approves enhanced specification**
- [ ] No code is written yet - specs define the contract

### Step 3: Implementation
- [ ] **Launch `core-development-expert` agent** with handover payload
- [ ] Agent implements components incrementally:
  - Backend implementations (RED → GREEN)
  - Backend mesh (RED → GREEN)
  - PromptTemplate chain (RED → GREEN)
  - Template helper enhancement with PromptTemplate (RED → GREEN)
  - Optional AiConfigFeature (RED → GREEN)
- [ ] Quality checks pass after each component (`ddev exec composer quality`)

### Step 4: Integration Testing
- [ ] End-to-end tests with real backends (OpenAI, Ollama)
- [ ] Mesh extensibility testing (adding custom backends)
- [ ] PromptTemplate chain extensibility testing
- [ ] AsyncFeature cooperation testing (streaming)
- [ ] Documentation updates

### Success Criteria

- [ ] All specs have passing tests (1:1 mapping)
- [ ] `ddev exec composer quality` passes (PSR-12, Psalm level 1, all tests)
- [ ] Mesh-based architecture (no rigid backend manager)
- [ ] PromptTemplate chain implemented (provider-specific prompt formatting)
- [ ] Template helpers accept `backend=` parameter
- [ ] Template helpers use PromptTemplate for formatting
- [ ] Mesh extensibility verified (custom backends can be added)
- [ ] PromptTemplate chain extensibility verified (custom templates can be added)
- [ ] No custom events created (AiErrorEvent removed)
- [ ] No AI config stored in context state
- [ ] Integration with AsyncFeature tested
- [ ] All three backends working (OpenAI, Ollama, Anthropic)
- [ ] Optional AiConfigFeature for credential injection
- [ ] No modifications to `/vendor/` directory

---

## Key Design Principles

- **Mesh-Based**: Backends in Mesh (array-like, chain-backed storage)
- **Backend Names**: Reference by name (`openai`), not model patterns
- **No Config Objects**: No AiConfiguration class - optional credential injection only
- **Single Spec File**: All AI enhancements in `specs/features/ai.yaml`
- **Spec-First**: No code without approved specifications
- **TemplateFeature Integration**: Full utilization of existing template system
- **No Custom Events**: States handle native exceptions
- **Optional Parameter**: Template helpers accept `backend=` for selection
- **Provider-Agnostic**: Backend abstraction hides provider differences
- **Async-Ready**: Cooperative yielding for non-blocking execution
