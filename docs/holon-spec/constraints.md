# Holon Constraints

## Feature Order (CRITICAL)
```
1. ExtendedState        # MUST be first
2. JsonSchemaFeature    # After ExtendedState
3. ContextBroadcastFeature
4. TemplateFeature      # MUST be before AiFeature
5. AiFeature
6. AiConfigFeature
7. AsyncFeature         # MUST be after ExtendedState
8. MessageFeature
9. AbilitiesFeature     # Requires MessageFeature
10. AgenticFeature      # Requires Abilities + Ai
```

## Callback Pattern
All callbacks return closures. Type hint acts as event filter:
```php
// Catch-all (any event)
function(object $t): void { ... }

// Filtered (only UserAction events)
function(UserAction $t): void { ... }

// Guards MUST return bool
function(object $t): bool { return $this->get('ready') === true; }
```

## Async Actions
- MUST return `Generator`
- MUST `yield` at least once
- `$this->capture()` / `$this->complete()` return values directly (NOT generators)

## Context Helpers ($this->)
| Method | Returns | Requires |
|--------|---------|----------|
| `get($key, $default)` | mixed | ExtendedState |
| `set($key, $value)` | void | ExtendedState |
| `capture($prompt, $schema, $backend)` | array | AiFeature |
| `complete($prompt, $backend)` | string | AiFeature |
| `template($tpl)` | Generator | TemplateFeature |
| `abilities($name, $params)` | Message | AbilitiesFeature |
| `weave($intent, $opts)` | Generator | AgenticFeature |

## Broadcast Filtering (ContextBroadcastFeature)
| Level | Condition | Behavior |
|-------|-----------|----------|
| 1 | No JsonSchemaFeature | All `set()` emits ContextChange |
| 2 | JsonSchemaFeature loaded | Only schema properties emit |
| 3 | `broadcast: false` on property | That property silent |
| 4 | `context.broadcast: false` | All silent (region-wide) |

## Paths
Use container paths: `/var/www/html/...` (not host filesystem)

## YAML Tags
- `!php return fn()` - Inline callback
- `!php require '/path'` - Load file
- `!include file.yaml` - Include YAML
- `# language=injectablephp` - PHPStorm syntax highlighting (place before !php)
