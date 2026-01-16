# JsonSchemaFeature - Context Schema Validation

## Purpose

JsonSchemaFeature provides **JSON Schema-based validation** for ExtendedState context fields. It enables declaring typed context properties with defaults, descriptions, and validation rules that are enforced during machine execution.

**Key Value**: Type-safe context with automatic default initialization and schema validation.

## Dependencies

**CRITICAL**: JsonSchemaFeature requires ExtendedState:

```php
->enableFeatures(
    new ExtendedState(),      // Required - provides context storage
    new JsonSchemaFeature()   // Extends context with schema support
)
```

## Public API

### YAML Schema Definition

Define context schemas in YAML:

```yaml
context:
  schema:
    - name: progress
      type: integer
      default: 0
      description: Task completion percentage

    - name: status
      type: string
      default: "pending"
      description: Current processing status

    - name: items
      type: array
      default: []
      description: Items to process

    - name: config
      type: object
      default: {}
      description: Configuration options
```

### Schema Properties

Each schema entry supports:

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `name` | string | Yes | Context field name |
| `type` | string | Yes | JSON Schema type (string, integer, number, boolean, array, object) |
| `default` | mixed | No | Default value (set during build) |
| `description` | string | No | Human-readable description |

### Programmatic Schema

Use `AddJsonSchema` build step:

```php
use Noem\State\Feature\JsonSchema\AddJsonSchema;

$builder->addBuildStep(new AddJsonSchema([
    ['name' => 'progress', 'type' => 'integer', 'default' => 0],
    ['name' => 'status', 'type' => 'string', 'default' => 'pending'],
]));
```

## How It Works

### Build-Time Processing

1. JsonSchemaFeature extends the `context` schema to accept `schema` array
2. During build, `AddJsonSchema` build step processes schema definitions
3. Default values are set in context metadata before machine starts

```
YAML parsed
    ↓
Schema validated via LoaderChains\Schema
    ↓
EnhanceRegionBuilder processes context.schema
    ↓
AddJsonSchema build step added
    ↓
During build(): defaults initialized in context
    ↓
Machine starts with typed, initialized context
```

### Default Initialization

```yaml
context:
  schema:
    - name: counter
      type: integer
      default: 0
```

```php
// In state callback - counter is already 0
->onEnter('start', function() {
    $counter = $this->get('counter');  // Returns 0, not null
})
```

## Usage Patterns

### Basic Typed Context

```yaml
context:
  schema:
    - name: userId
      type: integer
      description: Current user ID

    - name: userName
      type: string
      default: "anonymous"
      description: Display name

    - name: isAdmin
      type: boolean
      default: false
      description: Admin privileges flag

states:
  - name: authenticated
    onEnter:
      - run: !php |
          return static function() {
              $name = $this->get('userName');  // "anonymous" by default
              $this->set('userName', 'John');
          };
```

### Complex Default Values

```yaml
context:
  schema:
    - name: config
      type: object
      default:
        timeout: 30
        retries: 3
        debug: false

    - name: queue
      type: array
      default: []
```

### Integration with PresentationFeature

JsonSchemaFeature enables PresentationFeature by providing schema definitions:

```yaml
context:
  schema:
    - name: progress
      type: integer
      default: 0

presentations:
  - key: progress           # Must match schema name
    label: Progress
    intent: Completion percentage
```

**Note**: PresentationFeature validates that every presentation key has a corresponding schema entry.

### Form Validation Pattern

```yaml
context:
  schema:
    - name: email
      type: string
      default: ""
      description: User email address

    - name: age
      type: integer
      description: User age (required, no default)
```

```php
->onAction('submit', function(object $trigger) {
    $email = $this->get('email');
    $age = $this->get('age');

    // Validate (schema ensures types are correct)
    if (empty($email) || $age === null) {
        $this->set('errors', ['Missing required fields']);
        return;
    }
})
```

## Architecture

### Component Overview

```
JsonSchemaFeature
    ├── Extends LoaderChains\Schema
    │   └── Adds 'schema' to context schema
    │
    └── Hooks EnhanceRegionBuilder
        └── Adds AddJsonSchema build step

AddJsonSchema (BuildStep)
    └── callback(): Sets default values in context metadata
```

### Schema Processing Flow

```
1. YAML: context.schema array defined
    ↓
2. LoaderChains\Schema validates structure
    ↓
3. EnhanceRegionBuilder detects context.schema
    ↓
4. AddJsonSchema build step created with schema array
    ↓
5. During build():
    - Meta chain retrieves context metadata
    - For each schema entry with default:
      - metadata[name] = default
    ↓
6. Machine starts with defaults populated
```

## Critical Idiosyncrasies

### 1. Requires ExtendedState First

```php
// ✅ CORRECT
new ExtendedState(), new JsonSchemaFeature()

// ❌ Will fail - no context schema to extend
new JsonSchemaFeature(), new ExtendedState()
```

### 2. Defaults Set at Build Time

Defaults are set during `build()`, not at runtime:

```php
// Default is set BEFORE any state callbacks run
$region = $builder->build();  // defaults initialized here

// First trigger - defaults already in place
$region->trigger($event);
```

### 3. No Runtime Validation

JsonSchemaFeature sets defaults but does NOT validate values at runtime:

```yaml
context:
  schema:
    - name: count
      type: integer
      default: 0
```

```php
// ⚠️ No error - runtime validation not enforced
$this->set('count', 'not an integer');
```

**Recommendation**: Use validation in your callbacks if runtime type checking is needed.

### 4. Schema Names Must Be Unique

```yaml
context:
  schema:
    # ❌ Duplicate name - second overwrites first
    - name: status
      type: string
      default: "pending"
    - name: status
      type: integer
      default: 0
```

### 5. Null vs Missing Default

```yaml
context:
  schema:
    # Has default - initialized to 0
    - name: withDefault
      type: integer
      default: 0

    # No default - NOT initialized (null)
    - name: noDefault
      type: integer
```

```php
$this->get('withDefault');  // 0
$this->get('noDefault');    // null
```

## Relationship to Other Features

| Feature | Relationship | Notes |
|---------|--------------|-------|
| **ExtendedState** | Required | Provides context storage to extend |
| **RegionLoader** | Integrates | Extends YAML schema |
| **PresentationFeature** | Used by | Validates presentation keys exist in schema |
| **AbilitiesFeature** | Complement | Can use schema for ability parameter validation |

## Files Reference

| File | Purpose |
|------|---------|
| `JsonSchemaFeature.php` | Schema extension and build step registration |
| `AddJsonSchema.php` | Build step that initializes defaults |

## Summary Checklist

When using JsonSchemaFeature:

- [ ] Load ExtendedState FIRST
- [ ] Define schema with name, type, and optional default
- [ ] Use appropriate JSON Schema types (string, integer, number, boolean, array, object)
- [ ] Remember: defaults set at build time, not runtime
- [ ] No runtime type validation—validate in callbacks if needed
- [ ] Schema names must be unique
- [ ] Fields without defaults remain null until set

---

**Spec**: `specs/features/jsonschema.yaml` (if exists)
**Status**: Stable, production-ready
