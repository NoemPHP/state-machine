# AbilitiesFeature - Schema-Validated Discoverable Operations

## Purpose

AbilitiesFeature provides **schema-validated, discoverable cross-region operations** built on MessageFeature's correlation system. It enables state machines to expose structured capabilities that external agents, supervisors, and orchestration frameworks can discover, validate, and invoke in a type-safe manner.

**Key Value**: Transforms state machines into introspectable API surfaces, enabling dynamic tool use by AI agents, automated testing through contract validation, and self-documenting workflows.

## Core Concepts

### What is an Ability?

An **ability** is a named, schema-validated operation that a state machine can perform. Unlike regular message handlers, abilities provide:
- **JSON Schema validation** for parameters and responses
- **Meta-reflexive enumeration** for runtime discovery
- **Structured documentation** through descriptions
- **Conditional exposure** via optional predicates

Think of abilities as "functions as data" - they're both executable and introspectable.

### Why Use Abilities?

**Use abilities when you need:**
- AI agents to discover and invoke machine capabilities dynamically
- Contract-validated operations for external integrations
- Self-documenting APIs for state machine workflows
- Type-safe cross-region communication with schema enforcement

**Don't use abilities for:**
- Internal state machine coordination (use Message/Subscription instead)
- Simple request-response without schema requirements
- Performance-critical tight loops (use direct method calls)

## Public API

### Registering Abilities

#### In State Callbacks

```php
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;

->onEnter('setup', function(object $trigger) {
    $registry = $this->builder->chainMail->get(AbilityRegistry::class);

    $registry->register(new AbilityDefinition(
        name: 'calculate-total',
        description: 'Calculates order total with tax and shipping',
        parameterSchema: [
            'type' => 'object',
            'properties' => [
                'subtotal' => ['type' => 'number'],
                'taxRate' => ['type' => 'number'],
            ],
            'required' => ['subtotal', 'taxRate'],
        ],
        responseSchema: [
            'type' => 'object',
            'properties' => [
                'total' => ['type' => 'number'],
                'tax' => ['type' => 'number'],
            ],
        ],
        handler: function(mixed $parameters): array {
            $subtotal = $parameters['subtotal'];
            $taxRate = $parameters['taxRate'];
            $tax = $subtotal * $taxRate;

            return [
                'total' => $subtotal + $tax,
                'tax' => $tax,
            ];
        }
    ));
})
```

#### With Optional Predicate (Conditional Exposure)

```php
$registry->register(new AbilityDefinition(
    name: 'admin-reset',
    description: 'Resets system state (admin only)',
    parameterSchema: [...],
    responseSchema: [...],
    handler: function($params) { /* ... */ },
    predicate: function() {
        // Only expose this ability when user is admin
        return $this->get('userRole') === 'admin';
    }
));
```

**Predicate Behavior**: When a predicate returns `false`, the ability is excluded from enumeration. This enables context-aware capability exposure.

### Invoking Abilities

#### Using $this->abilities() in State Callbacks

**CRITICAL**: Abilities use message correlation, so invocation returns an **AbilityMessage** that requires `.then()` to receive the response:

```php
->onAction('process', function(object $trigger) {
    // Invoke ability and handle response
    $this->abilities('calculate-total', [
        'subtotal' => 100.00,
        'taxRate' => 0.08,
    ])->then(function($response) {
        // $response->parameters contains the handler result
        $total = $response->parameters['total'];
        $this->set('orderTotal', $total);
    });
})
```

**Wrong Pattern** (direct return):
```php
// ❌ This will NOT work - abilities() returns a message, not the result
$result = $this->abilities('calculate-total', [...]);
```

**Correct Pattern** (with .then()):
```php
// ✅ Use .then() to receive the response
$this->abilities('calculate-total', [...])
    ->then(function($response) {
        $result = $response->parameters;
    });
```

#### With Async/Await Pattern

If AsyncFeature is loaded, abilities can be used with yield:

```php
->onAction('process', function(object $trigger) {
    $this->abilities('calculate-total', [
        'subtotal' => 100.00,
        'taxRate' => 0.08,
    ])->then(function($response) {
        $this->set('total', $response->parameters['total']);
    });

    yield;  // Wait for response to arrive

    $total = $this->get('total');
    // Use total here
})
```

### Discovering Abilities

#### enumerate-abilities (Built-in Discovery Ability)

AbilitiesFeature automatically registers a meta-ability called `enumerate-abilities` that lists all registered abilities:

```php
->onEnter('introspect', function(object $trigger) {
    $this->abilities('enumerate-abilities')
        ->then(function($response) {
            $abilities = $response->parameters['abilities'];
            // Array of ability definitions with name, description, schemas
            foreach ($abilities as $ability) {
                echo "{$ability['name']}: {$ability['description']}\n";
            }
        });
})
```

**Response Format**:
```php
[
    'abilities' => [
        [
            'name' => 'calculate-total',
            'description' => 'Calculates order total...',
            'parameterSchema' => [...],
            'responseSchema' => [...],
        ],
        // ... more abilities
    ]
]
```

**Predicate Filtering**: Only abilities whose predicates return `true` (or have no predicate) are included.

## Architecture

### Dependency Requirements

```php
#[RequiresFeature(MessageFeature::class)]
class AbilitiesFeature implements Feature
```

**Why MessageFeature?** Abilities use message correlation for request-response matching. Without MessageFeature, responses couldn't be delivered back to the invoking callback.

### Service Registration

AbilitiesFeature registers the following in ChainMail:

1. **AbilityRegistry** - Mesh-based storage for ability definitions
2. **InvokeAbility** - Chain for ability invocation logic
3. **ExecuteAbilityHandler** - Chain for handler execution (hookable by AsyncFeature)
4. **ProcessAbilityResult** - Chain for result processing (hookable by AsyncFeature)

### BoundAccess Integration

The feature wires `$this->abilities()` into ExtendedState's BoundAccess:

```php
// In state callbacks, this is available automatically:
$this->abilities('ability-name', $parameters)
```

This returns an `AbilityMessage` that implements message correlation.

## Integration Patterns

### With InteractionRegistryFeature

Combine abilities with interaction discovery for complete introspection:

```php
// Register ability that uses interactions
$registry->register(new AbilityDefinition(
    name: 'get-user-confirmation',
    description: 'Requests yes/no confirmation from user',
    parameterSchema: [
        'type' => 'object',
        'properties' => [
            'question' => ['type' => 'string'],
        ],
    ],
    responseSchema: [
        'type' => 'object',
        'properties' => [
            'confirmed' => ['type' => 'boolean'],
        ],
    ],
    handler: function($params) {
        $response = null;

        $this->confirm($params['question'])
            ->then(function($msg) use (&$response) {
                $response = ['confirmed' => $msg->confirmed];
            });

        // Wait for response in async context
        while ($response === null) {
            yield;
        }

        return $response;
    }
));
```

### With AgenticFeature

AgenticFeature's `weave()` automatically discovers abilities via `enumerate-abilities` for AI-driven tool selection.

### Schema Validation

Abilities automatically validate parameters and responses against their schemas:

```php
// Invalid parameters throw an exception
$this->abilities('calculate-total', [
    'subtotal' => 'not-a-number',  // ❌ Schema violation
]);
```

## Advanced Usage

### Ability Middleware

Extend `ProcessAbilityResult` or `ExecuteAbilityHandler` chains to intercept ability execution:

```php
use Noem\State\Feature\Abilities\ExecuteAbilityHandler;

$chainMail->use(function(?ExecuteAbilityHandler $chain) {
    if ($chain === null) return;

    $chain->link(function($params, callable $next) {
        // Log ability invocation
        error_log("Invoking: {$params->abilityName}");

        $result = $next($params);

        // Log result
        error_log("Result: " . json_encode($result));

        return $result;
    });
});
```

### Dynamic Ability Registration

Register abilities at runtime based on state:

```php
->onEnter('admin-mode', function(object $trigger) {
    $registry = $this->builder->chainMail->get(AbilityRegistry::class);

    // Only register admin abilities when in admin mode
    $registry->register(new AbilityDefinition(
        name: 'delete-all-data',
        description: 'Deletes all system data',
        // ...
    ));
})
```

## Common Patterns

### Tool Registry Pattern

```php
// Register multiple related abilities as a "tool set"
$tools = [
    'file.read' => ['description' => 'Reads file content', ...],
    'file.write' => ['description' => 'Writes to file', ...],
    'file.delete' => ['description' => 'Deletes file', ...],
];

foreach ($tools as $name => $config) {
    $registry->register(new AbilityDefinition(
        name: $name,
        ...$config
    ));
}
```

### Capability Negotiation Pattern

```php
->onEnter('initialize', function(object $trigger) {
    // Discover what the machine can do
    $this->abilities('enumerate-abilities')
        ->then(function($response) {
            $capabilities = array_column(
                $response->parameters['abilities'],
                'name'
            );

            $this->set('capabilities', $capabilities);
        });

    yield;

    // Now use capabilities
    if (in_array('advanced-calculation', $this->get('capabilities'))) {
        // Use advanced calculation
    }
})
```

## Troubleshooting

### "Ability not found" Error

**Problem**: Invoking an ability that hasn't been registered.

**Solution**: Ensure the ability is registered before invocation, or check predicate logic isn't filtering it out.

### Response Never Arrives

**Problem**: Using abilities without `.then()` callback or message correlation.

**Solution**: Always use `.then()` to receive responses:
```php
$this->abilities('name', $params)
    ->then(function($response) {
        // Handle response here
    });
```

### Schema Validation Errors

**Problem**: Parameters don't match the defined schema.

**Solution**: Check parameter structure matches `parameterSchema` exactly. Use JSON Schema validation tools to verify.

## Performance Considerations

- **Ability enumeration is cheap** - `enumerate-abilities` returns cached definitions
- **Handler execution is synchronous** - Use AsyncFeature for async handlers
- **Schema validation has overhead** - Keep schemas focused and minimal
- **Predicates run on every enumeration** - Keep predicate logic simple

## Related Features

- **MessageFeature** (required) - Provides correlation infrastructure
- **ExtendedState** (optional) - Enables `$this->abilities()` syntax
- **InteractionFeature** (complementary) - Request-response patterns for external agents
- **InteractionRegistryFeature** (complementary) - Declarative contract discovery
- **AgenticFeature** (consumer) - AI-driven tool selection via ability discovery

## Specs

See `specs/features/abilities.yaml` for complete acceptance criteria and test coverage.
