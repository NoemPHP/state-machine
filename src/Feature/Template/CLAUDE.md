# TemplateFeature - Dynamic Content Generation

## Purpose

TemplateFeature provides **Handlebars-like template rendering** for state machine callbacks. Templates are compiled into generators that yield content chunks, enabling streaming output and integration with async operations.

**Key Value**: Enables dynamic content generation within state machines, with support for variables, loops, conditionals, custom helpers, and async I/O—all while streaming results incrementally.

## Public API

### Template Method

Templates are accessed via `$this->template()` in callbacks:

```php
->onAction('state', function(object $trigger) {
    $template = $this->template('Hello {{name}}!');
    // $template is a Generator that yields chunks

    // Option 1: Manual iteration
    while ($template->valid()) {
        $chunk = $template->current();
        // Process chunk...
        $template->next();
        yield;  // If async
    }
    $result = $template->getReturn();  // Full rendered string

    // Option 2: With Call::call (async)
    $result = yield Call::call($this->template('Hello {{name}}!'));
})
```

**Return Value**: Generator that yields string chunks and returns the complete rendered string.

### Template Syntax

#### Variables

```handlebars
{{variable}}         <!-- Unescaped variable -->
{{{variable}}}       <!-- HTML-escaped variable -->
```

**Context Access**:
```php
// In callback with ExtendedState:
$this->set('name', 'Alice');
$this->set('count', 42);

$template = $this->template('Hello {{name}}, count: {{count}}');
// Renders: "Hello Alice, count: 42"
```

#### Sections (Block Helpers)

```handlebars
{{#helper arg1 arg2 key=value}}
  Block content
{{/helper}}
```

**Nested Sections**:
```handlebars
{{#each items}}
  {{#if active}}
    Item: {{this}}
  {{/if}}
{{/each}}
```

### Built-in Helpers

#### `each` - Iteration

```handlebars
{{#each items}}
  {{this}}
{{/each}}
```

**Usage**:
```php
$this->set('items', ['apple', 'banana', 'cherry']);
$template = $this->template('{{#each items}}{{this}} {{/each}}');
// Renders: "apple banana cherry "
```

**Context**: Inside `{{#each}}`, `{{this}}` refers to the current item.

#### `if` - Conditional

```handlebars
{{#if condition}}
  Content when true
{{/if}}
```

**Usage**:
```php
$this->set('isActive', true);
$template = $this->template('{{#if isActive}}Active{{/if}}');
// Renders: "Active"
```

#### `else` - Inverse Conditional

```handlebars
{{#else condition}}
  Content when false
{{/else}}
```

**Usage**:
```php
$this->set('isActive', false);
$template = $this->template('{{#else isActive}}Inactive{{/else}}');
// Renders: "Inactive"
```

**Note**: `else` is a separate helper, not paired with `if`. Use it independently.

#### `include` - Template Inclusion

```handlebars
{{#include templatePath}}{{/include}}
```

**Template Resolution**:
1. Looks in `machines/template/{templatePath}`
2. Falls back to variable value as file path

**Usage**:
```php
// File: machines/template/header.hbs
// Content: "=== {{title}} ==="

$this->set('title', 'Welcome');
$template = $this->template('{{#include "header.hbs"}}{{/include}}');
// Renders: "=== Welcome ==="
```

### Custom Helpers

Register custom helpers via the `Helpers` class:

```php
use Noem\State\Feature\Template\Helpers;
use Noem\State\Feature\Template\Compiler\Invocation;

$helpers = new Helpers();
$helpers->registerHelper('uppercase', function (Invocation $data, callable $next) {
    $text = $data->args[0] ?? '';
    yield strtoupper($text);
    yield from $next($data);
});

// In template:
// {{uppercase "hello"}}
// Renders: "HELLO"
```

**Helper Signature**:
```php
function(Invocation $data, callable $next): \Generator
```

**Invocation Properties**:
- `$data->data` - Template context (variables)
- `$data->args` - Positional arguments
- `$data->hash` - Named arguments (key=value pairs)
- `$data->blockContent` - Inner content (for block helpers)
- `$data->buffer` - Output buffer

### Helper Arguments

#### Positional Arguments

```handlebars
{{helper arg1 arg2 arg3}}
```

**Access**:
```php
function(Invocation $data, callable $next) {
    $first = $data->args[0];
    $second = $data->args[1];
    // ...
}
```

#### Named Arguments (Hash)

```handlebars
{{helper arg1 key1="value1" key2=true key3=42}}
```

**Access**:
```php
function(Invocation $data, callable $next) {
    $value1 = $data->hash['key1'];  // "value1"
    $value2 = $data->hash['key2'];  // true
    $value3 = $data->hash['key3'];  // 42
    // ...
}
```

**Supported Types**:
- Strings: `"text"` or `'text'`
- Booleans: `true`, `false`
- Numbers: `42`, `3.14`
- Null: `null`

### Block Helpers

Block helpers can access and render their inner content:

```php
$helpers->registerHelper('custom', function (Invocation $data, callable $next) {
    yield 'Before: ';
    yield from $data->blockContent();  // Render inner content
    yield ' :After';
    yield from $next($data);
});
```

**Usage**:
```handlebars
{{#custom}}
  Inner content here
{{/custom}}
```

**Renders**: "Before: Inner content here :After"

## Usage Patterns

### Simple Variable Replacement

```php
$region = (new RegionBuilder())
    ->enableFeatures(
        new ExtendedState(),
        new TemplateFeature()
    )
    ->setStates('greeting')
    ->onEnter('greeting', function(object $trigger) {
        $this->set('user', 'Alice');
        $this->set('time', 'morning');

        $template = $this->template('Good {{time}}, {{user}}!');
        while ($template->valid()) {
            echo $template->current();
            $template->next();
        }
    })
    ->build();
```

### Async Template Rendering

```php
use Noem\State\Feature\Async\Call;

->onAction('render', function(object $trigger) {
    // Async rendering with Call::call
    $result = yield Call::call(
        $this->template('Hello {{name}}!')
    );

    $this->set('output', $result);
})
```

### Iteration with Each

```php
$this->set('items', [
    ['name' => 'Item 1', 'price' => 10],
    ['name' => 'Item 2', 'price' => 20],
]);

$template = $this->template(<<<TPL
{{#each items}}
  {{this.name}}: \${{this.price}}
{{/each}}
TPL
);
```

**Note**: Nested properties like `{{this.name}}` are **not supported**. The template system accesses array keys directly, so `{{this}}` inside `{{#each}}` provides the entire item. To access nested properties, use custom helpers.

### Conditional Rendering

```php
$this->set('showWelcome', true);
$this->set('isAdmin', false);

$template = $this->template(<<<TPL
{{#if showWelcome}}
  Welcome!
{{/if}}
{{#else isAdmin}}
  Regular user view
{{/else}}
TPL
);
```

### Custom Helper with Arguments

```php
$helpers->registerHelper('formatPrice', function (Invocation $data, callable $next) {
    $amount = $data->args[0];
    $currency = $data->hash['currency'] ?? 'USD';
    $formatted = number_format($amount, 2);

    yield "{$currency} {$formatted}";
    yield from $next($data);
});

// In template:
// {{formatPrice 1234.5 currency="EUR"}}
// Renders: "EUR 1234.50"
```

### Async I/O in Helpers

```php
use Noem\State\Feature\Async\IO\Load;

$helpers->registerHelper('loadFile', function (Invocation $data, callable $next) {
    $filename = $data->args[0];
    $load = new Load($filename);

    yield from $load();
    yield from $next($data);
});

// In template:
// {{#loadFile "content.txt"}}{{/loadFile}}
// Streams file content
```

### HTML Escaping

```php
$this->set('userInput', '<script>alert("XSS")</script>');

// Escaped (safe):
$template = $this->template('Input: {{{userInput}}}');
// Renders: "Input: &lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;"

// Unescaped (dangerous):
$template = $this->template('Input: {{userInput}}');
// Renders: "Input: <script>alert("XSS")</script>"
```

**Best Practice**: Always use `{{{triple}}}` for user-provided content unless you explicitly need raw HTML.

## Integration Points

### With ExtendedState (REQUIRED)

TemplateFeature accesses context via ExtendedState's `Meta` chain:

```php
// TemplateFeature.php:36
$metaData = $meta->call(new Params\Meta($params->region, ContextMetaType::get()));
```

**This means**:
- Templates access the same context as `$this->get()`/`$this->set()`
- Context variables are automatically available in templates
- No explicit data passing needed

**Example**:
```php
->onEnter('state', function(object $trigger) {
    $this->set('name', 'Alice');
    $this->set('age', 30);

    // Template automatically has access to 'name' and 'age'
    $result = yield Call::call($this->template('{{name}} is {{age}}'));
})
```

### With AsyncFeature

Templates return generators, making them async-compatible:

```php
->onAction('state', function(object $trigger) {
    $template = $this->template('Long content...');

    // Manual async iteration
    while ($template->valid()) {
        $template->next();
        yield;  // Pause between chunks
    }

    // Or use Call::call
    $result = yield Call::call($this->template('Content'));
})
```

### With BoundAccess Chain

TemplateFeature registers the `template()` method via `BoundAccess`:

```php
// TemplateFeature.php:30-52
$boundAccess->link(function (BoundAccessParams $params, callable $next) {
    if ($params->name === 'template') {
        $key = $params->payload[0];
        $template = $templateFactory->create($key);
        return (function() use ($template, $metaData) {
            // Return generator...
        })();
    }
    return $next($params);
});
```

**This means**: `$this->template()` is available in ALL callbacks when TemplateFeature is loaded.

### With RegionLoader

**Note**: TemplateFeature does **NOT** extend YAML schema like AsyncFeature does. Templates are created programmatically in callbacks.

However, templates can be stored as strings in context:

```yaml
context:
  welcomeTemplate: "Hello {{name}}!"
```

```php
->onEnter('state', function(object $trigger) {
    $templateStr = $this->get('welcomeTemplate');
    $template = $this->template($templateStr);
    // ...
})
```

## Architecture

### Component Overview

```
TemplateFeature
    ├── TemplateFactory (compiles templates)
    │   ├── Tokenizer → Nodes
    │   ├── Nodes → Middleware chain
    │   └── Returns callable that produces Generator
    │
    ├── Helpers (built-in + custom helpers)
    │   ├── each
    │   ├── if
    │   ├── else
    │   └── include
    │
    ├── Invocation (execution context)
    │   ├── data (template variables)
    │   ├── args (positional arguments)
    │   ├── hash (named arguments)
    │   └── blockContent (inner content chain)
    │
    ├── Tokenizer (parsing)
    │   ├── Scans template string
    │   ├── Identifies {{ }} and {{{ }}} delimiters
    │   └── Yields Node objects
    │
    └── Buffer (output accumulation)
        └── Collects chunks into final string
```

### Template Compilation Flow

```
Template String
    ↓
Tokenizer::process()
    ↓
Yields Node objects:
  - TEXT
  - VARIABLE_ESCAPE (triple braces)
  - VARIABLE_UNESCAPE (double braces)
  - SECTION_OPEN ({{#helper}})
  - SECTION_CLOSE ({{/helper}})
    ↓
TemplateFactory::render()
    ↓
Builds Chain of middleware:
  - Text → yield text
  - Variable → lookup & yield value
  - Section → invoke helper → yield result
    ↓
Returns callable(context): Generator
```

### Execution Flow

```php
// User calls:
$template = $this->template('Hello {{name}}');

// Internal flow:
1. BoundAccess intercepts 'template' method
2. TemplateFactory::create('Hello {{name}}')
3. Tokenizer parses string → Nodes
4. TemplateFactory builds Chain
5. Returns Generator that:
   a. Executes chain with context
   b. Yields chunks
   c. Returns complete string
```

### Helper Execution

```
{{#helper arg1 key=value}}
  Block content
{{/helper}}

Execution:
1. parseArguments('helper arg1 key=value')
   → name: 'helper'
   → args: ['arg1']
   → hash: ['key' => 'value']

2. Create Invocation:
   → data: context (from $this->get/set)
   → args: ['arg1']
   → hash: ['key' => 'value']
   → blockContent: Chain for inner content

3. Invoke helper:
   $helpers['helper']($invocation, $next)

4. Helper yields results:
   yield 'prefix';
   yield from $invocation->blockContent();
   yield 'suffix';
```

## Critical Idiosyncrasies

### 1. Template Argument is String, Not Name

`$this->template()` takes the **full template string**, not a template name/reference:

```php
// ❌ WRONG - Not a template reference system
$template = $this->template('myTemplate');

// ✅ CORRECT - Inline template string
$template = $this->template('Hello {{name}}!');

// ✅ CORRECT - Template from variable
$templateStr = $this->get('templateString');
$template = $this->template($templateStr);
```

**Why**: The first argument to `$this->template()` is passed to `TemplateFactory::create()` which expects the full template markup, not a lookup key.

### 2. Generator Return vs Yielded Values

Templates yield chunks but **return** the complete string:

```php
$template = $this->template('Hello');

// Yields: 'H', 'e', 'l', 'l', 'o' (character by character)
// Returns: 'Hello' (complete string)

// To get complete string:
$result = $template->getReturn();  // After iteration completes

// Or with Call::call:
$result = yield Call::call($template);  // Gets return value
```

**Reference**: TemplateFeature.php:41-52 shows the generator yields chunks while building a result string.

### 3. Context is Mesh, Not Array

Templates receive `Mesh` (from ExtendedState's Meta chain), not a plain array:

```php
// In TemplateFeature.php:36
$metaData = $meta->call(new Params\Meta($params->region, ContextMetaType::get()));

// $metaData is Mesh (implements ArrayAccess)
$template($metaData);
```

**Implication**: Template variables are resolved via `Mesh::offsetGet()`, which triggers middleware including Ornament resolvers.

**This means**: Templates can access async resolvers transparently!

```yaml
context:
  resolvers:
    - name: userData
      run: !php "return function() { yield; return fetchUser(); };"
```

```php
// In template:
$template = $this->template('User: {{userData}}');
// Automatically triggers resolver computation!
```

### 4. No Nested Property Access

The template system does **not** support dotted paths:

```php
// ❌ WRONG - Dotted paths don't work
$this->set('user', ['name' => 'Alice', 'age' => 30]);
$template = $this->template('{{user.name}}');  // Looks for key 'user.name', not nested

// ✅ CORRECT - Flatten or use helper
$this->set('userName', 'Alice');
$template = $this->template('{{userName}}');
```

**Why**: `getValue()` in TemplateFactory.php:216-235 does a simple `$data[$key]` lookup without path parsing.

### 5. Each Helper Modifies Context

The `{{#each}}` helper creates a new context where `{{this}}` points to the current item:

```php
// In Helpers.php:17-29
foreach ($innerData as $thing) {
    $newData = $data->setData(['this' => $thing]);
    yield from $data->blockContent($newData);
}
```

**Implication**: Inside `{{#each}}`, only `{{this}}` is available, not outer context:

```php
$this->set('items', ['A', 'B']);
$this->set('prefix', 'Item');

$template = $this->template(<<<TPL
{{#each items}}
  {{prefix}}: {{this}}
{{/each}}
TPL
);
// Renders: ": A: B" (prefix is lost inside each!)
```

**Workaround**: Access outer context before `{{#each}}` or pass as helper argument.

### 6. Helper Chain Order Matters

Helpers use `yield from $next($data)` to continue the chain:

```php
$helpers->registerHelper('wrapper', function (Invocation $data, callable $next) {
    yield '[';
    yield from $next($data);  // IMPORTANT: calls next middleware
    yield ']';
});
```

**Missing `yield from $next()`**: Breaks the chain, rest of template won't render.

### 7. Include Helper Path Resolution

The `include` helper has hardcoded path resolution:

```php
// In Helpers.php:54
$dir = getcwd();
$maybeFilename = $dir . '/machines/template/' . $fragment;
```

**Implication**:
- Templates **must** be in `machines/template/` directory
- Paths are relative to current working directory
- No configurable template roots (marked as TODO)

### 8. HTML Escaping is Basic

Escaping uses PHP's `htmlspecialchars()`:

```php
// In TemplateFactory.php:231
return htmlspecialchars($escapedValue, ENT_QUOTES, 'UTF-8');
```

**Implication**:
- Good for basic HTML escaping
- Not suitable for other contexts (JavaScript, CSS, URLs)
- No configurable escaping strategy

### 9. Template Compilation is Per-Execution

Templates are compiled **every time** `$this->template()` is called:

```php
// Each call recompiles:
->onAction('state', function() {
    $t1 = $this->template('Hello {{name}}');  // Compile
    $t2 = $this->template('Hello {{name}}');  // Compile again
})
```

**No Caching**: Unlike some template engines, there's no compilation cache.

**Implication**: For frequently used templates, consider caching the result or using variables:

```php
// Better for repeated use:
->onEnter('state', function() {
    $templateStr = 'Hello {{name}}';
    // Store template string once, but still recompiles on each render
})
```

### 10. Argument Parsing is Regex-Based

Argument parsing uses regex (TemplateFactory.php:246-286):

```php
$regex = [
    '([a-zA-Z0-9]+\="[^"]*")',      // cat="meow"
    '([a-zA-Z0-9]+\=\'[^\']*\')',   // mouse='squeak'
    '([a-zA-Z0-9]+\=[a-zA-Z0-9\.]+)', // dog=false
    '("[^"]*")',                    // "quoted"
    '(\'[^\']*\')',                 // 'quoted'
    '([^\s]+)',                     // unquoted
];
```

**Limitations**:
- Quoted strings can't contain their quote character (no escaping)
- Whitespace in arguments must be quoted
- Complex expressions not supported

**Examples**:
```handlebars
{{helper "arg with spaces"}}  ✅ Works
{{helper arg-with-dashes}}    ✅ Works
{{helper "can't escape"}}     ❌ Breaks (apostrophe in double quotes)
{{helper arg=user.name}}      ❌ Doesn't parse nested (just literal string)
```

## Common Patterns

### Streaming Large Templates

```php
->onAction('stream', function(object $trigger) {
    $template = $this->template(<<<TPL
{{#each items}}
  Large content for {{this}}...
{{/each}}
TPL
    );

    // Stream chunks to output
    while ($template->valid()) {
        $chunk = $template->current();
        echo $chunk;
        flush();
        $template->next();
        yield;  // Async pause
    }
})
```

### Dynamic Template Selection

```php
->onAction('render', function(object $trigger) {
    $type = $this->get('contentType');

    $templates = [
        'html' => '<h1>{{title}}</h1><p>{{body}}</p>',
        'text' => '{{title}}\n\n{{body}}',
        'json' => '{"title":"{{title}}","body":"{{body}}"}',
    ];

    $templateStr = $templates[$type] ?? $templates['text'];
    $result = yield Call::call($this->template($templateStr));
})
```

### Reusable Fragments

```php
// Store fragments in context
->onEnter('setup', function() {
    $this->set('headerTpl', '=== {{title}} ===');
    $this->set('footerTpl', '--- {{copyright}} ---');
})

->onAction('render', function() {
    $header = yield Call::call($this->template($this->get('headerTpl')));
    $body = 'Content here';
    $footer = yield Call::call($this->template($this->get('footerTpl')));

    $this->set('page', $header . $body . $footer);
})
```

### Custom Helper for Nested Access

```php
$helpers->registerHelper('get', function (Invocation $data, callable $next) {
    $path = $data->args[0];
    $value = $data->data;

    foreach (explode('.', $path) as $key) {
        if (is_array($value) && isset($value[$key])) {
            $value = $value[$key];
        } else {
            $value = '';
            break;
        }
    }

    yield (string)$value;
    yield from $next($data);
});

// Now in templates:
// {{get "user.name"}}
```

## Testing Patterns

### Testing Template Rendering

```php
public function testTemplateRendersVariables(): void
{
    $factory = new TemplateFactory(new Helpers());
    $template = $factory->create('Hello {{name}}!');

    $generator = $template(['name' => 'Alice']);

    $result = '';
    foreach ($generator as $chunk) {
        $result .= $chunk;
    }

    $this->assertSame('Hello Alice!', $result);
}
```

### Testing Custom Helpers

```php
public function testCustomHelperWithArgs(): void
{
    $helpers = new Helpers();
    $helpers->registerHelper('repeat', function (Invocation $data, callable $next) {
        $text = $data->args[0];
        $times = $data->hash['times'] ?? 1;

        for ($i = 0; $i < $times; $i++) {
            yield $text;
        }
        yield from $next($data);
    });

    $factory = new TemplateFactory($helpers);
    $template = $factory->create('{{repeat "X" times=3}}');

    $result = '';
    foreach ($template() as $chunk) {
        $result .= $chunk;
    }

    $this->assertSame('XXX', $result);
}
```

### Integration Testing

```php
public function testTemplateWithExtendedState(): void
{
    $region = (new RegionBuilder())
        ->enableFeatures(
            new ExtendedState(),
            new TemplateFeature()
        )
        ->setStates('active')
        ->onAction('active', function(object $t) {
            $this->set('user', 'Bob');
            $result = yield Call::call($this->template('Hi {{user}}'));
            $this->set('output', $result);
        })
        ->build();

    $region->trigger(new \stdClass());
    // Additional triggers for async completion...

    $this->assertRegionContext($region, 'output', 'Hi Bob');
}
```

## When to Use TemplateFeature

### ✅ Use When

- Generating dynamic text content (HTML, plain text, JSON)
- Building responses for state machines (HTTP responses, messages)
- Composing content from context variables
- Need streaming/chunked output
- Conditionals and loops in output
- Integrating with async I/O for template parts

### ❌ Avoid When

- Complex template logic (use dedicated template engine)
- Need advanced features (layouts, partials, filters)
- Performance critical (compilation overhead)
- Need template caching
- Complex nested data structures (no dotted paths)

## Relationship to Other Features

| Feature | Relationship | Notes |
|---------|--------------|-------|
| **ExtendedState** | Depends on | Accesses context via Meta chain |
| **AsyncFeature** | Works with | Templates are generators, async-compatible |
| **BoundAccess** | Provides API | Registers `$this->template()` method |
| **AiFeature** | Complementary | AI can generate content, templates format it |
| **RegionLoader** | Independent | No YAML schema extension |

## Performance Considerations

### Compilation Overhead

- No template caching
- Each `$this->template()` call recompiles
- Tokenization is character-by-character (O(n) where n = template length)

**Recommendation**: For frequently used templates, store strings in variables but understand compilation still happens on each render.

### Memory Usage

- Generators yield chunks incrementally
- Buffer accumulates complete string
- Large templates use memory for both chunks and final result

### Streaming Benefits

- Templates yield as they compile
- Enables responsive output for large content
- Can interleave with async I/O operations

## Files Reference

| File | Purpose |
|------|---------|
| `src/Feature/Template/TemplateFeature.php` | Main feature implementation |
| `src/Feature/Template/Helpers.php` | Built-in and custom helper registry |
| `src/Feature/Template/Compiler/TemplateFactory.php` | Template compilation engine |
| `src/Feature/Template/Compiler/Tokenizer.php` | Template parsing (string → Nodes) |
| `src/Feature/Template/Compiler/Invocation.php` | Execution context for helpers |
| `src/Feature/Template/Compiler/Node.php` | AST node representation |
| `src/Feature/Template/Compiler/NodeType.php` | Node type enumeration |
| `src/Feature/Template/Buffer.php` | Output accumulation |

## Summary Checklist

When working with TemplateFeature:

- [ ] `$this->template()` takes full template string, not name/reference
- [ ] Templates return Generator (yields chunks, returns complete string)
- [ ] Use `Call::call()` to get final result in async callbacks
- [ ] Variables come from ExtendedState context (`$this->get/set`)
- [ ] Templates can trigger async resolvers transparently
- [ ] `{{variable}}` is unescaped, `{{{variable}}}` is HTML-escaped
- [ ] No nested property access (no `{{user.name}}`)
- [ ] `{{#each}}` changes context - outer variables not accessible
- [ ] Helpers must `yield from $next($data)` to continue chain
- [ ] Include helper looks in `machines/template/` directory
- [ ] No template compilation cache (recompiles each time)
- [ ] Argument parsing has limitations (no quote escaping, no complex expressions)
- [ ] Custom helpers are generators with `Invocation` and `$next` parameters

---

**Last Updated**: 2025-11-28
**Feature Status**: Stable, production-ready
**Spec Coverage**: Integration tests for core functionality
**Known Limitations**: No caching, no nested paths, hardcoded template directory
