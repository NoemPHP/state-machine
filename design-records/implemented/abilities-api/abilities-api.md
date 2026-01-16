# Abilities API

**Status**: Implemented
**Created**: 2025-12-22
**Related**: [MessageFeature](../../../src/Feature/Message/), [AbilitiesFeature](../../../src/Feature/Abilities/)

## Overview

The Abilities API provides a **standardized framework for cross-region interactions** through schema-validated, discoverable capabilities. Built on MessageFeature's correlation system, it enables regions to expose and invoke operations through a clean, direct API.

**Design Principles**:
1. **Synchronous-First**: Core works without AsyncFeature; async is enhancement
2. **Self-Describing**: Enumeration itself is an ability (meta-reflexive)
3. **Direct Integration**: Wired directly into BoundAccess - no intermediate objects
4. **Stateless Chain**: Follows DispatchAction/Notification/InvokeCallback pattern

## Architecture

###High-Level Design

```
┌───────────────────────────────────────────────────────────────┐
│                    ChainMail Container                         │
├───────────────────────────────────────────────────────────────┤
│                                                                │
│  ┌────────────────┐         ┌──────────────────┐             │
│  │ AbilityRegistry│◄────────┤ AbilityDefinition│             │
│  │   (Mesh)       │         │   (Schema)       │             │
│  └────────┬───────┘         └──────────────────┘             │
│           │ provides                                           │
│           ▼                                                    │
│  ┌────────────────────┐                                       │
│  │  InvokeAbility     │──────┐                                │
│  │    (Chain)         │      │ wired into                     │
│  │                    │      │                                │
│  │  Middleware:       │      │                                │
│  │  ┌────────┐        │      ▼                                │
│  │  │Logging │        │  ┌─────────────────┐                 │
│  │  ├────────┤        │  │  BoundAccess    │                 │
│  │  │  Auth  │        │  │   (Chain)       │                 │
│  │  ├────────┤        │  │                 │                 │
│  │  │ Cache  │        │  │ $this->abilities│                 │
│  │  ├────────┤        │  │      ('name')   │                 │
│  │  │Provider│        │  └─────────────────┘                 │
│  └────────────────────┘                                       │
│           │                                                    │
│           │ triggers                                           │
│           ▼                                                    │
│  ┌──────────────────────┐                                     │
│  │   MessageFeature     │                                     │
│  │   (Correlation)      │                                     │
│  └──────────────────────┘                                     │
└───────────────────────────────────────────────────────────────┘

Flow:
  $this->abilities('name', $params)
      ↓
  BoundAccess intercepts __call
      ↓
  Creates Params\InvokeAbility
      ↓
  InvokeAbility chain processes (with middleware)
      ↓
  Region->trigger(AbilityMessage)
      ↓
  Handler executes + response via MessageFeature
```

## Components

### 1. AbilityDefinition

```php
class AbilityDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameterSchema, // JSON Schema
        public readonly array $responseSchema,  // JSON Schema
        public readonly callable $handler
    ) {}
}
```

### 2. AbilityRegistry (Mesh)

```php
class AbilityRegistry extends Mesh
{
    public function register(AbilityDefinition $ability): void
    {
        $this[$ability->name] = $ability;
    }

    public function get(string $name): ?AbilityDefinition
    {
        return $this[$name] ?? null;
    }

    public function all(): array
    {
        return iterator_to_array($this);
    }
}
```

### 3. AbilityMessage

```php
class AbilityMessage extends Message
{
    public function __construct(
        public readonly string $abilityName,
        public readonly mixed $parameters,
        public readonly ?AbilityDefinition $definition = null,
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }
}
```

### 4. Params\InvokeAbility

```php
namespace Noem\State\Feature\Abilities\Chains\Params;

class InvokeAbility
{
    public function __construct(
        public readonly Region $region,
        public readonly string $abilityName,
        public readonly mixed $parameters = null
    ) {}
}
```

### 5. InvokeAbility Chain

```php
namespace Noem\State\Feature\Abilities\Chains;

/**
 * @template-extends Chain<Params\InvokeAbility, AbilityMessage>
 */
class InvokeAbility extends Chain
{
    public function __construct(private readonly AbilityRegistry $registry)
    {
        parent::__construct($this->handleInvocation(...));
    }

    private function handleInvocation(Params\InvokeAbility $params): AbilityMessage
    {
        $definition = $this->registry->get($params->abilityName);

        if (!$definition) {
            throw new AbilityNotFoundException($params->abilityName);
        }

        // Validate parameters
        $this->validateParameters($params->parameters, $definition->parameterSchema);

        // Create message
        $message = new AbilityMessage(
            $params->abilityName,
            $params->parameters,
            $definition
        );

        // Dispatch (sync or async depending on features loaded)
        $params->region->trigger($message);

        return $message;
    }

    private function validateParameters(mixed $parameters, array $schema): void
    {
        // JSON Schema validation
    }
}
```

**Why This Pattern?**
- ✅ Stateless - no SplObjectStorage needed
- ✅ Follows DispatchAction/Notification pattern exactly
- ✅ Params object encapsulates invocation data
- ✅ Provider function contains core logic
- ✅ Middleware intercepts via `link()`

### 6. AbilitiesFeature

```php
class AbilitiesFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        // Supply registry
        $chainMail->supply(fn(): AbilityRegistry => new AbilityRegistry());

        // Supply chain
        $chainMail->supply(fn(AbilityRegistry $registry): InvokeAbility =>
            new InvokeAbility($registry)
        );

        // Wire into BoundAccess
        $chainMail->use($this->installBoundAccessMethod(...));

        // Handle ability messages
        $chainMail->use($this->installAbilityHandling(...));

        // Register built-in abilities
        $chainMail->use($this->registerBuiltInAbilities(...));
    }

    private function installBoundAccessMethod(
        BoundAccess $boundAccess,
        InvokeAbility $chain
    ): void {
        $boundAccess->link(function (BoundAccessParams $params, callable $next) use ($chain) {
            if ($params->type !== BoundAccessParams::TYPE_METHOD) {
                return $next($params);
            }

            if ($params->name === 'abilities') {
                // $params->payload = [$abilityName, $parameters]
                [$abilityName, $parameters] = $params->payload + [null, null];

                return $chain->call(
                    new Params\InvokeAbility($params->region, $abilityName, $parameters)
                );
            }

            return $next($params);
        });
    }

    private function registerBuiltInAbilities(AbilityRegistry $registry): void
    {
        $registry->register(new AbilityDefinition(
            name: 'enumerate-abilities',
            description: 'List all available abilities',
            parameterSchema: [
                'type' => 'object',
                'properties' => [
                    'filter' => ['type' => 'string', 'description' => 'Regex filter']
                ]
            ],
            responseSchema: [
                'type' => 'object',
                'properties' => [
                    'abilities' => ['type' => 'array']
                ]
            ],
            handler: function (array $params) use ($registry): array {
                $abilities = array_map(
                    fn($def) => [
                        'name' => $def->name,
                        'description' => $def->description,
                        'parameterSchema' => $def->parameterSchema,
                        'responseSchema' => $def->responseSchema,
                    ],
                    array_values($registry->all())
                );

                if (isset($params['filter'])) {
                    $abilities = array_filter(
                        $abilities,
                        fn($a) => preg_match($params['filter'], $a['name'])
                    );
                }

                return ['abilities' => array_values($abilities)];
            }
        ));
    }

    private function installAbilityHandling(
        DispatchAction $dispatchChain,
        AbilityRegistry $registry,
        Notification $notificationChain
    ): void {
        $dispatchChain->link(function (Action $action, callable $next) use ($registry, $notificationChain) {
            $result = $next($action);

            if (!$action->payload instanceof AbilityMessage) {
                return $result;
            }

            $message = $action->payload;
            $definition = $registry->get($message->abilityName);

            if (!$definition) {
                throw new AbilityNotFoundException($message->abilityName);
            }

            // Execute handler
            $responseData = ($definition->handler)($message->parameters);

            // Create response
            $response = $message->createResponse(
                AbilityMessage::class,
                [
                    'abilityName' => $message->abilityName,
                    'parameters' => $responseData,
                ]
            );

            // Emit
            $notificationChain->call(new Notify($action->region, $response));

            return $result;
        });
    }
}
```

## Usage

### Basic Invocation

```php
$region = (new RegionBuilder())
    ->enableFeatures(
        new ExtendedState(),
        new MessageFeature(),
        new AbilitiesFeature()
    )
    ->setStates('working')
    ->onEnter('working', function (object $t) {
        // Direct method call
        $this->abilities('calculate-total', ['numbers' => [1, 2, 3]])
            ->then(function (AbilityMessage $response) {
                echo $response->parameters['total']; // 6
            });
    })
    ->build();
```

### Registering Abilities

```php
$registry = $region->chainMail->get(AbilityRegistry::class);

$registry->register(new AbilityDefinition(
    name: 'calculate-total',
    description: 'Sum an array of numbers',
    parameterSchema: [
        'type' => 'object',
        'properties' => [
            'numbers' => ['type' => 'array', 'items' => ['type' => 'number']]
        ],
        'required' => ['numbers']
    ],
    responseSchema: [
        'type' => 'object',
        'properties' => [
            'total' => ['type' => 'number']
        ]
    ],
    handler: fn(array $params) => ['total' => array_sum($params['numbers'])]
));
```

### Enumeration (Meta-Reflexive)

```php
// Enumeration is itself an ability
$this->abilities('enumerate-abilities')->then(function (AbilityMessage $response) {
    foreach ($response->parameters['abilities'] as $ability) {
        echo "{$ability['name']}: {$ability['description']}\n";
    }
});

// With filter
$this->abilities('enumerate-abilities', ['filter' => '/^calc/'])
    ->then(function (AbilityMessage $response) {
        // Only abilities matching /^calc/
    });
```

### Adding Middleware

```php
// Access the chain
$chain = $region->chainMail->get(InvokeAbility::class);

// Add logging
$chain->link(function (Params\InvokeAbility $params, callable $next) {
    error_log("Invoking: {$params->abilityName}");
    $start = hrtime(true);

    $result = $next($params);

    $duration = (hrtime(true) - $start) / 1e6;
    error_log("Completed in {$duration}ms");

    return $result;
});

// Add authorization
$chain->link(function (Params\InvokeAbility $params, callable $next) {
    $user = $params->region->chainMail
        ->get(ExtendedState::class)
        ->call(new Params\Get($params->region, 'currentUser'));

    if (!$user->can($params->abilityName)) {
        throw new UnauthorizedException();
    }

    return $next($params);
});
```

## Synchronous vs Async

### Without AsyncFeature (Synchronous)

```php
$region = (new RegionBuilder())
    ->enableFeatures(
        new ExtendedState(),
        new MessageFeature(),
        new AbilitiesFeature()
        // NO AsyncFeature
    )
    ->onEnter('working', function (object $t) {
        $result = null;

        $this->abilities('calculate', ['x' => 5])
            ->then(function ($response) use (&$result) {
                // Fires BEFORE abilities() returns
                $result = $response->parameters['value'];
            });

        // $result available immediately
        echo $result; // Works!
    })
    ->build();
```

### With AsyncFeature (Parallel Execution)

```php
$region = (new RegionBuilder())
    ->enableFeatures(
        new ExtendedState(),
        new AsyncFeature(),       // Enable async
        new MessageFeature(),
        new AbilitiesFeature()
    )
    ->onEnter('working', function (object $t) {
        // Parallel invocations
        yield from [
            $this->abilities('task-1', ['data' => 'A']),
            $this->abilities('task-2', ['data' => 'B']),
            $this->abilities('task-3', ['data' => 'C']),
        ];

        // All three execute concurrently
    })
    ->build();
```

## Key Design Decisions

1. **Direct BoundAccess Integration**
   - No AbilityInvoker or BoundAbilityInvoker intermediate objects
   - Wired directly into BoundAccess via `__call` interception
   - API: `$this->abilities('name', $params)` - clean and direct
   - Follows ExtendedState pattern (`$this->set()`, `$this->get()`)

2. **Stateless Chain**
   - No SplObjectStorage needed
   - Chain is purely functional - processes params, returns message
   - Any state belongs in abilities themselves or ExtendedState
   - Follows DispatchAction/Notification pattern exactly

3. **Meta-Reflexive Enumeration**
   - `enumerate-abilities` registered as built-in ability
   - Enables cross-region discovery
   - AI can introspect via same mechanism
   - Consistent API - everything is an ability

4. **Synchronous-First**
   - Core works with just MessageFeature
   - AsyncFeature is progressive enhancement
   - Same API works in both modes
   - `then()` callbacks: sync by default, async when enhanced

## References

- **Chain Pattern**: `src/Middleware/Chain.php`
- **DispatchAction**: `src/Chains/DispatchAction.php` (similar pattern)
- **Notification**: `src/Chains/Notification.php` (similar pattern)
- **BoundAccess**: `src/Feature/ExtendedState/ContextChains/BoundAccess.php`
- **ExtendedState**: `src/Feature/ExtendedState/ExtendedState.php` (similar BoundAccess wiring)
- **MessageFeature**: `src/Feature/Message/MessageFeature.php`
