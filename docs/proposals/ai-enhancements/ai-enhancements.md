# AI Feature Multi-Backend Enhancement Proposal

**Status**: Draft
**Author**: Claude Code
**Date**: 2025-12-13
**Related Components**: AiFeature, Completion, Chat, RequestBuilder

## Overview

This proposal outlines enhancements to the AI Feature to support multiple backend providers (OpenAI, Ollama, Anthropic, etc.) and model-specific message templates. The current implementation is hardcoded for a single backend with limited flexibility for different AI providers.

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

## Proposed Architecture

### 1. Backend Abstraction Layer

Introduce `BackendInterface` for provider-agnostic API interaction:

```php
interface BackendInterface
{
    public function isSupportedModel(string $model): bool;
    public function createCompletionRequest(Request $request): array;
    public function createChatRequest(Request $request): array;
    public function getEndpoint(string $type): string;
    public function getDefaultHeaders(): array;
    public function transformResponse(array $response, string $type): array;
    public function getName(): string;
}
```

### 2. Backend Implementations

Concrete adapters for each provider:

**OpenAiBackend**: Handles GPT models with chat/completions API
**OllamaBackend**: Handles local models with generate/chat APIs
**AnthropicBackend**: Handles Claude models with messages API

### 3. Backend Manager

Central registry for backend discovery and configuration:

```php
class BackendManager
{
    public function getBackend(string $model): BackendInterface;
    public function getBackendByName(string $name): BackendInterface;
    public function mapModel(string $model, string $backendName): void;

    private function detectProvider(string $model): string
    {
        return match(true) {
            str_starts_with($model, 'gpt-') => 'openai',
            str_starts_with($model, 'claude-') => 'anthropic',
            str_contains($model, ':') => 'ollama',
            default => 'unknown'
        };
    }
}
```

### 4. Template System Enhancement

Model and provider-specific templates with inheritance:

```php
interface TemplateInterface
{
    public function render(TemplateContext $context): string;
    public function getVariables(): array;
}

class TemplateRegistry
{
    public function register(string $provider, string $type, TemplateInterface $template): void;
    public function inherit(string $child, string $parent): void;

    public function get(string $model, string $type): TemplateInterface
    {
        // 1. Try model-specific template
        if (isset($this->templates[$model][$type])) {
            return $this->templates[$model][$type];
        }

        // 2. Try provider template
        $provider = $this->detectProvider($model);
        if (isset($this->templates[$provider][$type])) {
            return $this->templates[$provider][$type];
        }

        // 3. Fall back to base template
        return $this->templates['base'][$type];
    }
}
```

### 5. Configuration System

YAML-based configuration for backends and defaults:

```yaml
ai:
  backends:
    openai:
      baseUrl: 'https://api.openai.com/v1'
      token: '%env(AI_OPENAI_TOKEN)%'
      models: ['gpt-4', 'gpt-3.5-turbo', 'text-davinci-003']
      defaults:
        temperature: 0.7
        maxTokens: 2048

    ollama:
      baseUrl: 'http://localhost:11434'
      token: null
      models: ['llama3:8b', 'codellama:7b', 'mistral:7b']
      defaults:
        temperature: 0.6
        maxTokens: 4096

    anthropic:
      baseUrl: 'https://api.anthropic.com'
      token: '%env(AI_ANTHROPIC_TOKEN)%'
      models: ['claude-3-opus', 'claude-3-sonnet']
      defaults:
        temperature: 0.5
        maxTokens: 4096

  defaults:
    backend: 'openai'
    model: 'gpt-3.5-turbo'

  templates:
    path: '%kernel.project_dir%/templates/ai'
    cache: '%kernel.cache_dir%/ai_templates'

  features:
    streaming: true
    json_mode: true
    tools: false
```

### 6. Enhanced Features

- **Dynamic Backend Resolution**: Auto-detect backend based on model name
- **Template Inheritance**: Provider-specific templates with model overrides
- **Configuration Loading**: YamlLoader for environment-specific config
- **Runtime Backend Switching**: Choose backend per request
- **Enhanced Error Handling**: Provider-specific error mapping

## Implementation Strategy

### Phase 1: Core Abstractions
1. Create `BackendInterface` and basic implementations
2. Implement `BackendManager` with auto-detection
3. Add unit tests for each backend adapter

### Phase 2: Template System
1. Create `TemplateInterface` and `TemplateContext`
2. Implement `TemplateRegistry` with inheritance
3. Add Mustache template engine integration

### Phase 3: Configuration System
1. Create `AiConfiguration` DTO
2. Implement YAML `ConfigurationLoader`
3. Add environment variable resolution

### Phase 4: Integration
1. Modify `Completion/Chat` to use backend abstraction
2. Update `RequestBuilder` to load config defaults
3. Enhance `AiFeature` template helpers
4. Maintain backward compatibility

## Backward Compatibility

**Current Usage** (continues to work):
```php
$completion = new Completion('Complete this code');
$result = iterator_to_array($completion());
```

**Enhanced Usage**:
```php
$config = AiConfiguration::fromFile('ai.yaml');
$manager = BackendManager::fromConfiguration($config);

// Auto-detect backend
$completion = new Completion('Complete this code with GPT-4');

// Or specify explicitly
$backend = $manager->getBackend('gpt-4');
$completion = new Completion('Complete this code', $backend);
```

## Benefits

1. **Extensibility**: Easy to add new providers (Gemini, Cohere, etc.)
2. **Quality**: Model-specific prompts improve response quality
3. **Flexibility**: Runtime backend/model switching
4. **Maintainability**: Clear separation of provider-specific logic
5. **Testing**: Isolated adapter testing without API calls

## Migration Path

### For Feature Developers
- No breaking changes to existing interface
- Optional enhancement through new backend parameter

### For Feature Extenders
```php
// Before
class CustomCompletion extends Completion {
    protected function buildRequest(): array {
        // Custom request building logic
    }
}

// After
class CustomBackend implements BackendInterface {
    public function createCompletionRequest(Request $request): array {
        // Provider-specific request building
    }
}
```

## Open Questions

1. **Template Format**: Continue with hardcoded prompts or adopt Mustache?
2. **Config File Location**: Place in project root or `config/` directory?
3. **Caching Strategy**: Template caching for production performance?
4. **Error Handling**: Standardize error responses across providers?

## Next Steps

1. **Review**: Gather feedback on proposed architecture
2. **Prototype**: Implement Phase 1 core abstractions
3. **Validate**: Test with multiple providers
4. **Refine**: Update based on real-world usage
5. **Spec & Implement**: Follow spec-driven development workflow

---

*Strategic analysis of the AI Feature architecture reveals that the proposed multi-backend enhancement provides a clean, extensible foundation for supporting diverse AI providers while preserving the elegant generator-based design. The modular architecture with BackendInterface abstraction and Template inheritance system enables seamless integration of new providers and model-specific optimizations without breaking existing functionality.*