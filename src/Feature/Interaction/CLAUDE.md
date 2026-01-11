# InteractionFeature - Standardized Human-Machine Interaction Patterns

## Purpose

InteractionFeature provides **standardized patterns for machines to request information and decisions from external agents** - including humans, supervisors, orchestration frameworks, and AI agents. It defines four core interaction types (confirm, select, choice, prompt) with request-response message pairs that enable framework adapters to render appropriate UIs while maintaining type-safe communication.

**Key Value**: Separates interaction intent from UI implementation, enabling state machines to work with any framework adapter (CLI, web UI, Slack, Discord, AI agent) without code changes.

## Core Concepts

### What is an Interaction?

An **interaction** is a request-response cycle where the state machine asks an external agent for input. Unlike regular messages, interactions:
- **Block machine progress** until a response arrives
- **Have specific patterns** (yes/no, pick one, pick many, free text)
- **Include context** like question text, options, defaults, and constraints
- **Use message correlation** to automatically deliver responses back to the waiting callback

### Interaction Types

| Type | Purpose | Request Message | Response Message |
|------|---------|----------------|------------------|
| **confirm** | Yes/No question | `ConfirmRequest` | `ConfirmResponse` |
| **select** | Pick one option | `SelectRequest` | `SelectResponse` |
| **choice** | Pick multiple options | `ChoiceRequest` | `ChoiceResponse` |
| **prompt** | Free-text input | `PromptRequest` | `PromptResponse` |

## Public API

### Confirm Pattern (Yes/No)

Ask a yes/no question with optional default:

```php
->onAction('deploy', function(object $trigger) {
    $confirmed = null;

    $this->confirm(
        question: 'Deploy to production?',
        defaultValue: false
    )->then(function($response) use (&$confirmed) {
        $confirmed = $response->confirmed;
    });

    yield;  // Wait for response

    if ($confirmed) {
        // Proceed with deployment
    }
})
```

**Message Structure**:
```php
// Request
ConfirmRequest {
    question: 'Deploy to production?',
    defaultValue: false,
    context: null,
    timeoutMs: null
}

// Response
ConfirmResponse {
    confirmed: true,   // User's answer
    cancelled: false   // Whether timeout/cancel occurred
}
```

**Use cases**: Dangerous operations, user consent, feature gates

### Select Pattern (Pick One)

Choose one option from a list:

```php
->onAction('configure', function(object $trigger) {
    $backend = null;

    $this->select(
        question: 'Choose database backend',
        options: [
            'mysql' => 'MySQL 8.0',
            'postgres' => 'PostgreSQL 14',
            'sqlite' => 'SQLite 3',
        ]
    )->then(function($response) use (&$backend) {
        $backend = $response->selectedKey;
    });

    yield;  // Wait for response

    $this->set('dbBackend', $backend);
})
```

**Message Structure**:
```php
// Request
SelectRequest {
    question: 'Choose database backend',
    options: ['mysql' => 'MySQL 8.0', ...],
    defaultKey: null,
    context: null,
    timeoutMs: null
}

// Response
SelectResponse {
    selectedKey: 'postgres',  // Selected option key
    cancelled: false
}
```

**Use cases**: Single-choice configuration, menu navigation, mode selection

### Choice Pattern (Pick Many)

Choose multiple options from a list:

```php
->onAction('configure', function(object $trigger) {
    $features = null;

    $this->choice(
        question: 'Select features to enable',
        options: [
            'cache' => 'Redis caching',
            'search' => 'Elasticsearch',
            'queue' => 'Job queue',
        ],
        minSelections: 1,
        maxSelections: null  // Unlimited
    )->then(function($response) use (&$features) {
        $features = $response->selectedKeys;
    });

    yield;  // Wait for response

    $this->set('enabledFeatures', $features);
})
```

**Message Structure**:
```php
// Request
ChoiceRequest {
    question: 'Select features to enable',
    options: ['cache' => 'Redis caching', ...],
    minSelections: 1,
    maxSelections: null,
    defaultKeys: [],
    context: null,
    timeoutMs: null
}

// Response
ChoiceResponse {
    selectedKeys: ['cache', 'queue'],  // Array of selected keys
    cancelled: false
}
```

**Use cases**: Feature selection, permission configuration, multi-step setup

### Prompt Pattern (Free Text)

Request free-text input with optional validation:

```php
->onAction('setup', function(object $trigger) {
    $name = null;

    $this->prompt(
        question: 'Enter your name',
        placeholder: 'John Doe',
        constraints: [
            'minLength' => 2,
            'maxLength' => 50,
            'pattern' => '^[A-Za-z ]+$',
        ]
    )->then(function($response) use (&$name) {
        $name = $response->value;
    });

    yield;  // Wait for response

    $this->set('userName', $name);
})
```

**Message Structure**:
```php
// Request
PromptRequest {
    question: 'Enter your name',
    defaultValue: null,
    placeholder: 'John Doe',
    constraints: ['minLength' => 2, ...],
    context: null,
    timeoutMs: null
}

// Response
PromptResponse {
    value: 'Jane Smith',  // User's input
    cancelled: false
}
```

**Use cases**: Configuration input, user data collection, search queries

## Advanced Features

### Context and Metadata

All interaction requests accept optional `context` for additional information:

```php
$this->confirm(
    question: 'Proceed?',
    defaultValue: false,
    context: [
        'helpText' => 'This will delete all data',
        'severity' => 'critical',
        'affectedRecords' => 1234,
    ]
)
```

Framework adapters can use context to enhance UI rendering (e.g., show help text, change color based on severity).

### Timeout Handling

Set timeout in milliseconds:

```php
$this->confirm(
    question: 'Confirm within 30 seconds',
    defaultValue: false,
    timeoutMs: 30000
)->then(function($response) {
    if ($response->cancelled) {
        // Timeout occurred - use default
        $confirmed = false;
    } else {
        $confirmed = $response->confirmed;
    }
});
```

**Behavior**: When timeout expires, response arrives with `cancelled: true`.

### Cancellation

Users can cancel interactions (via UI or framework adapter):

```php
$this->select(
    question: 'Choose option',
    options: ['a' => 'A', 'b' => 'B']
)->then(function($response) {
    if ($response->cancelled) {
        // User cancelled - handle gracefully
        return;
    }

    $selected = $response->selectedKey;
    // Process selection
});
```

## BoundAccess Helpers

InteractionFeature registers these helper methods in ExtendedState:

| Method | Interaction Type | Returns |
|--------|-----------------|---------|
| `$this->confirm($q, $default, $ctx, $timeout)` | Confirm | `ConfirmRequest` |
| `$this->select($q, $opts, $default, $ctx, $timeout)` | Select | `SelectRequest` |
| `$this->choice($q, $opts, $min, $max, $defaults, $ctx, $timeout)` | Choice | `ChoiceRequest` |
| `$this->prompt($q, $default, $placeholder, $constraints, $ctx, $timeout)` | Prompt | `PromptRequest` |

**Usage**: All helpers return request messages that support `.then()` for receiving responses.

## Integration with InteractionRegistryFeature

### Declarative Contract Registration

When combined with **InteractionRegistryFeature**, you can declare expected interactions upfront for external agent introspection:

```php
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;

$registry = $builder->chainMail->get(InteractionRegistry::class);

// Declare interaction contract before runtime
$registry->register(new InteractionDefinition(
    id: 'deploy_confirm',
    type: 'confirm',
    state: 'deploying',
    question: 'Deploy to production?',
    metadata: ['defaultValue' => false]
));
```

**Benefits**:
- External agents can query expected interactions via `enumerate-interactions` ability
- UI frameworks can pre-generate forms before execution
- Contract validation ensures runtime requests match declarations
- Documentation auto-generation from interaction registry

### Runtime Registration

Register interactions dynamically in state callbacks:

```php
->onEnter('setup', function(object $trigger) {
    $this->registerInteraction('user_name', new InteractionDefinition(
        id: 'user_name',
        type: 'prompt',
        state: 'setup',
        question: 'Enter your name',
        constraints: ['minLength' => 2]
    ));

    // Interaction is now discoverable via abilities
    $this->abilities('get-interactions-for-state', ['state' => 'setup'])
        ->then(function($response) {
            // Contains 'user_name' interaction
        });
})
```

### Discovery Workflow

```php
// 1. External agent discovers interactions before execution
$this->abilities('enumerate-interactions')
    ->then(function($response) {
        $interactions = $response->parameters['interactions'];
        // Pre-generate UI forms for all interactions
    });

yield;

// 2. Machine executes and sends interaction requests
$this->confirm('Deploy?')->then(function($r) {
    // Handle response
});

// 3. Framework adapter matches request to pre-generated form
// 4. User interacts with form
// 5. Response delivered back to machine
```

## Architecture

### Dependency Requirements

```php
#[RequiresFeature(MessageFeature::class)]
class InteractionFeature implements Feature
```

**Why MessageFeature?** All interaction requests/responses extend `Message` and use correlation IDs to automatically route responses back to waiting callbacks.

### Message Correlation Flow

```
1. State callback invokes $this->confirm('Question?')
   ↓
2. ConfirmRequest created with correlation ID
   ↓
3. Request dispatched to subscribers via MessageFeature
   ↓
4. Framework adapter receives request, renders UI
   ↓
5. User responds, adapter sends ConfirmResponse with same correlation ID
   ↓
6. MessageFeature correlates response to request
   ↓
7. .then() callback invoked with response
   ↓
8. State callback continues execution
```

### Service Registration

InteractionFeature registers in ChainMail:
- **BoundAccess integration** for `$this->confirm()`, `$this->select()`, etc.
- **Message dispatching** for request/response routing

## Common Patterns

### Wizard Pattern

Multi-step form with validation:

```php
->onEnter('setup-wizard', function(object $trigger) {
    // Step 1: Get name
    $name = null;
    $this->prompt('Enter name')->then(function($r) use (&$name) {
        $name = $r->value;
    });
    yield;

    // Step 2: Select role
    $role = null;
    $this->select('Select role', [
        'admin' => 'Administrator',
        'user' => 'Regular User',
    ])->then(function($r) use (&$role) {
        $role = $r->selectedKey;
    });
    yield;

    // Step 3: Confirm
    $confirmed = null;
    $this->confirm("Create $role account for $name?")->then(function($r) use (&$confirmed) {
        $confirmed = $r->confirmed;
    });
    yield;

    if ($confirmed) {
        // Create account
        $this->set('userName', $name);
        $this->set('userRole', $role);
    }
})
```

### Conditional Interaction Pattern

Only ask if needed:

```php
->onAction('process', function(object $trigger) {
    $hasPermission = $this->get('userRole') === 'admin';

    if (!$hasPermission) {
        // Request admin approval
        $approved = null;
        $this->confirm('Request admin approval?')->then(function($r) use (&$approved) {
            $approved = $r->confirmed;
        });
        yield;

        if (!$approved) {
            return;  // Abort
        }
    }

    // Proceed with operation
})
```

### Retry Pattern with Interaction

```php
->onAction('deploy', function(object $trigger) {
    $retries = 0;
    $success = false;

    while (!$success && $retries < 3) {
        $success = attemptDeployment();

        if (!$success) {
            $retry = null;
            $this->confirm("Deployment failed. Retry?")->then(function($r) use (&$retry) {
                $retry = $r->confirmed;
            });
            yield;

            if (!$retry) {
                break;
            }

            $retries++;
        }
    }
})
```

## Framework Adapter Integration

### Subscribing to Interactions

Framework adapters subscribe to interaction requests:

```php
$region->subscribe(ConfirmRequest::class, function(ConfirmRequest $request) {
    // Render UI (CLI, web form, Slack modal, etc.)
    echo "{$request->question} (y/n): ";
    $answer = readline();

    // Send response
    $region->dispatch(new ConfirmResponse(
        confirmed: $answer === 'y',
        cancelled: false,
        correlationId: $request->correlationId
    ));
});
```

### Example: CLI Adapter

```php
// Subscribe to all interaction types
$region->subscribe(InteractionRequest::class, function(InteractionRequest $request) {
    $response = match($request->getType()) {
        'confirm' => handleConfirm($request),
        'select' => handleSelect($request),
        'choice' => handleChoice($request),
        'prompt' => handlePrompt($request),
    };

    $region->dispatch($response);
});

function handleConfirm(ConfirmRequest $request): ConfirmResponse {
    echo "{$request->question} ";
    echo $request->defaultValue ? "(Y/n): " : "(y/N): ";

    $input = trim(readline());
    $confirmed = match(strtolower($input)) {
        'y', 'yes' => true,
        'n', 'no' => false,
        '' => $request->defaultValue,
        default => $request->defaultValue,
    };

    return new ConfirmResponse($confirmed, false, $request->correlationId);
}
```

## Troubleshooting

### Response Never Arrives

**Problem**: Callback blocks forever waiting for interaction response.

**Cause**: No framework adapter is subscribed to handle the request.

**Solution**: Ensure a framework adapter subscribes to `InteractionRequest::class` or specific types.

### Correlation Mismatch

**Problem**: Response delivered to wrong callback or not at all.

**Cause**: Response sent with different correlation ID than request.

**Solution**: Always use `$request->correlationId` when creating responses:
```php
new ConfirmResponse($confirmed, false, $request->correlationId)
```

### UI Not Updating

**Problem**: Multiple interactions fire but UI only shows first.

**Cause**: Framework adapter not handling multiple simultaneous interactions.

**Solution**: Queue interactions and process sequentially, or implement multi-modal UI.

## Performance Considerations

- **Interactions block machine progress** - Use timeouts to prevent indefinite blocking
- **Message dispatch overhead** - Each interaction creates 2 messages (request + response)
- **UI rendering is framework-dependent** - Async UIs may have latency
- **Correlation tracking uses memory** - MessageFeature maintains correlation table

## Related Features

- **MessageFeature** (required) - Provides correlation infrastructure
- **ExtendedState** (optional) - Enables `$this->confirm()` syntax
- **InteractionRegistryFeature** (complementary) - Declarative contract discovery
- **AbilitiesFeature** (complementary) - Introspectable operation patterns
- **AgenticFeature** (consumer) - AI agents can use interaction patterns

## Specs

See `specs/features/interaction.yaml` and `specs/features/interaction-registry.yaml` for complete acceptance criteria and test coverage.
