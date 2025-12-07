# MessageFeature Proposal - Call → Response Messaging Pipeline
# Built on SubscriptionFeature Infrastructure

## Executive Summary

Create **MessageFeature** - an advanced use case of SubscriptionFeature that implements a **call-response messaging pipeline** with UUID-based correlation and promise-like syntax.

### At the highest level:
- Connects **incoming triggers to outgoing events** (Call → Response pattern)
- **Request-Reply** semantics using subscription infrastructure
- **Promise-like then()** method for fluent API
- **UUID correlation** between request and response messages
- **Time-based correlation** for request-response matching

### Architecture Pattern:

```php
// 1. Create correlated message
$message = new DataRequest($requestPayload);
$message->then(function($response) {
    echo "Received response: " . $response->data;
});

// 2. Dispatch to region
$region->trigger($message);  // MessageFeature sets up temporary subscription

// 3. Trigger finds matching response event with same UUID
// 4. Response delivered to then() callback
```

---

## Core Components

### 1. Abstract Message Class

**File:** `src/Feature/Message/Message.php`

```php
<?php
declare(strict_types=1);

namespace Noem\State\Feature\Message;

/**
 * Abstract message with UUID correlation and promise-like API
 *
 * Message objects must be fully JSON-serializable for persistence and transmission.
 * Subclasses implement their own serialization logic for full control.
 */
abstract class Message implements \JsonSerializable
{
    protected readonly string $correlationId;

    /**
     * @var callable[] Registered response handlers
     */
    private array $replyHandlers = [];

    protected function __construct(?string $correlationId = null) {
        $this->correlationId = $correlationId ?? $this->generateId();
    }

    private function generateId(): string {
        return sprintf('%08x-%04x-%04x-%04x-%012x',
            random_int(0, 0xFFFFFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFFFFFFFFFF)
        );
    }

    /**
     * Get correlation ID for matching request/response
     */
    final public function correlationId(): string
    {
        return $this->correlationId;
    }

    /**
     * Register response handler (promise-like API)
     */
    final public function then(callable $handler): self
    {
        $this->replyHandlers[] = $handler;
        return $this;
    }

    /**
     * Deliver response to registered handlers
     */
    final public function deliverResponse(Message $response): void
    {
        foreach ($this->replyHandlers as $handler) {
            $handler($response);  // Call handler with response Message object
        }
    }

    /**
     * Check if this message replies to given request
     */
    final public function repliesTo(Message $request): bool
    {
        return $this->correlationId === $request->correlationId();
    }

    /**
     * Create a correlated response message
     *
     * @template T of Message
     * @param class-string<T> $responseClass
     * @param mixed $responsePayload
     * @return T
     */
    final public function createResponse(string $responseClass, mixed $responsePayload): Message
    {
        // Type check that response class exists and extends Message
        if (!class_exists($responseClass) || !is_subclass_of($responseClass, self::class)) {
            throw new \InvalidArgumentException(
                "Response class must exist and extend " . self::class
            );
        }

        return $responseClass::fromData($responsePayload, $this->correlationId);
    }

    // Abstract methods - subclasses MUST implement
    abstract public function jsonSerialize(): mixed;
    abstract protected static function fromData(mixed $data, ?string $correlationId): static;

    /**
     * Reconstruct Message from JSON-decoded array
     *
     * Expected format:
     * [
     *   'type' => 'Fully\\Qualified\\ClassName',  // Optional if $fqcn provided
     *   'correlationId' => 'uuid-string',
     *   'data' => [...] // Message-specific payload
     * ]
     *
     * @param array $data JSON-decoded message data
     * @param string|null $fqcn Optional fully-qualified class name (overrides $data['type'])
     * @return static Reconstructed message instance
     */
    public static function fromJson(array $data, ?string $fqcn = null): static {
        $fqcn = $fqcn ?? ($data['type'] ?? null);

        if ($fqcn && is_subclass_of($fqcn, self::class)) {
            return $fqcn::fromData($data['data'] ?? null, $data['correlationId'] ?? null);
        }

        // Fallback to anonymous class
        return self::createAnonymousMessage($data);
    }

    private static function createAnonymousMessage(array $data): static {
        return new class($data['data'] ?? new \stdClass(), $data['correlationId'] ?? null) extends Message {
            public function __construct(
                public readonly object $data,
                ?string $correlationId = null
            ) {
                parent::__construct($correlationId);
            }

            public function jsonSerialize(): mixed {
                return [
                    'correlationId' => $this->correlationId,
                    'type' => 'AnonymousMessage',
                    'data' => $this->data
                ];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static {
                return new self($data, $correlationId);
            }
        };
    }
}
```

### 2. Reflection Trait (Optional)

**File:** `src/Feature/Message/ReflectiveMessageSerialization.php`

```php
<?php
declare(strict_types=1);

namespace Noem\State\Feature\Message;

/**
 * Optional trait for reflection-based serialization
 * Use for simple Message classes where automatic serialization is sufficient
 */
trait ReflectiveMessageSerialization
{
    public function jsonSerialize(): mixed {
        $reflection = new \ReflectionClass($this);
        $properties = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getName() === 'correlationId') {
                continue;
            }
            $properties[$property->getName()] = $property->getValue($this);
        }

        return [
            'correlationId' => $this->correlationId,
            'type' => static::class,
            'data' => $properties
        ];
    }

    protected static function fromData(mixed $data, ?string $correlationId): static {
        if (!is_array($data)) {
            throw new \InvalidArgumentException(
                'ReflectiveMessageSerialization requires array payload. Got: ' . gettype($data)
            );
        }

        $reflection = new \ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new static($correlationId);
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            if ($param->getName() === 'correlationId') {
                $args[] = $correlationId;
            } else {
                $args[] = $data[$param->getName()] ?? null;
            }
        }

        return new static(...$args);
    }
}
```

### 3. MessageFeature Implementation

**File:** `src/Feature/Message/MessageFeature.php`

```php
<?php
declare(strict_types=1);

namespace Noem\State\Feature\Message;

use Noem\State\Chains\ChainMail;
use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\Feature;
use Noem\State\Region;
use Noem\State\RegionBuilder;

/**
 * Implements call-response messaging on subscription infrastructure
 */
class MessageFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use($this->installMessageDispatching(...));
    }

    /**
     * Add message dispatching middleware to action chain
     */
    private function installMessageDispatching(
        RegionBuilder $builder,
        Notification $notificationChain,
        DispatchAction $dispatchChain
    ): void {
        // Hook DispatchAction chain to scan for Messages
        $dispatchChain->link(function($action, $next) use ($notificationChain) {
            // Dispatch action first
            $result = $next($action);

            // Check if payload is a Message
            if (!$action->payload instanceof Message) {
                return $result;
            }

            // For Messages, set up temporary response subscription
            $this->setupMessageSubscription(
                $action->payload,
                $notificationChain
            );

            return $result;
        });
    }

    /**
     * Set up temporary subscription for message response
     */
    private function setupMessageSubscription(
        Message $message,
        Notification $notificationChain
    ): void {
        // Subscribe to events with correlation ID matching request
        $unsubscribe = $notificationChain->subscribe(
            function(object $event, ?Region $eventRegion = null) use ($message) {
                // Check if event is a Message responding to our request
                if (!($event instanceof Message)) {
                    return;
                }

                if (!$event->repliesTo($message)) {
                    return;
                }

                // Correlation matches - deliver response to request then() callbacks
                $message->deliverResponse($event);

                // Auto cleanup - remove this subscription since we got our response
                $unsubscribe();
            }
        );
    }
}
```

---

## Implementation Flow

### Phase 1: Core Message Infrastructure

**Files:**
- `src/Feature/Message/Message.php` - Abstract Message class
- `src/Feature/Message/ReflectiveMessageSerialization.php` - Optional reflection trait
- `src/Feature/Message/MessageFeature.php` - Core feature

**Specs in `specs/features/message.yaml`:**
```yaml
name: message
group: features
features:
  - name: message-dispatch
    specs:
      - acceptanceCriteria: MessageFeature registers action middleware for Message payloads
        criticality: contract
        intent: Enables call-response pattern on action dispatch chain
        test: tests/PHPUnit/Unit/Feature/Message/ActionMiddlewareRegistrationTest.php

      - acceptanceCriteria: Message instances generate unique UUID correlation IDs
        criticality: contract
        intent: Ensures each request can be uniquely correlated to its response
        test: tests/PHPUnit/Unit/Feature/Message/UuidCorrelationTest.php

      - acceptanceCriteria: Message.then() registers response handlers
        criticality: contract
        intent: Provides promise-like fluent API for response handling
        test: tests/PHPUnit/Unit/Feature/Message/ThenHandlerRegistrationTest.php

      - acceptanceCriteria: MessageFeature intercepts Message payloads in action chain
        criticality: contract
        intent: Triggers messaging pipeline when Message instances are dispatched
        test: tests/PHPUnit/Unit/Feature/Message/MessageInterceptionTest.php

  - name: correlation-matching
    specs:
      - acceptanceCriteria: Messages with matching correlation IDs are delivered to then() handlers
        criticality: contract
        intent: Enables request-response correlation in messaging pipeline
        test: tests/PHPUnit/Unit/Feature/Message/CorrelationDeliveryTest.php

      - acceptanceCriteria: Temporary subscriptions are deregistered after response delivery
        criticality: contract
        intent: Prevents memory leaks and ensures subscriptions don't persist beyond message lifetime
        test: tests/PHPUnit/Unit/Feature/Message/SubscriptionCleanupTest.php

  - name: json-serialization
    specs:
      - acceptanceCriteria: Message classes implement JsonSerializable with custom serialization
        criticality: contract
        intent: Ensures messages can be persisted and transmitted as JSON
        test: tests/PHPUnit/Unit/Feature/Message/JsonSerializableTest.php

      - acceptanceCriteria: Message.fromJson() reconstructs messages from JSON data
        criticality: contract
        intent: Enables bidirectional JSON serialization with type reconstruction
        test: tests/PHPUnit/Unit/Feature/Message/JsonReconstructionTest.php

      - acceptanceCriteria: ReflectiveMessageSerialization trait provides automatic serialization
        criticality: detail
        intent: Offers zero-boilerplate serialization for simple message types
        test: tests/PHPUnit/Unit/Feature/Message/ReflectionTraitTest.php

  - name: response-creation
    specs:
      - acceptanceCriteria: Message.createResponse() creates correlated response instances
        criticality: contract
        intent: Provides type-safe response creation with automatic correlation ID injection
        test: tests/PHPUnit/Unit/Feature/Message/CreateResponseTest.php

      - acceptanceCriteria: createResponse() validates response class extends Message
        criticality: contract
        intent: Ensures type safety in response message creation
        test: tests/PHPUnit/Unit/Feature/Message/CreateResponseTypeValidationTest.php
```

### Phase 2: SubscriptionFeature Enhancements

**Additional specs in `specs/features/subscription.yaml`:**
```yaml
  - name: message-correlation-filtering
    specs:
      - acceptanceCriteria: SubscriptionFeature pre-filters listeners for Message events by correlation ID
        criticality: detail
        intent: Optimizes message delivery by avoiding unnecessary type checking for unmatched correlations
        test: tests/PHPUnit/Unit/Feature/Subscription/MessageCorrelationFilteringTest.php
```

---

## Usage Examples

### Example 1: Basic Request-Response

```php
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;

// Set up region with messaging
$region = (new RegionBuilder())
    ->enableFeatures(
        new SubscriptionFeature(),
        new MessageFeature()
    )
    ->setStates('idle', 'processing', 'done')
    ->build();

// Define message types
class DataRequest extends Message {
    public function __construct(
        public readonly string $query,
        public readonly array $filters = [],
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }

    public function jsonSerialize(): mixed {
        return [
            'correlationId' => $this->correlationId,
            'type' => static::class,
            'data' => [
                'query' => $this->query,
                'filters' => $this->filters
            ]
        ];
    }

    protected static function fromData(mixed $data, ?string $correlationId): static {
        return new self(
            query: $data['query'] ?? '',
            filters: $data['filters'] ?? [],
            correlationId: $correlationId
        );
    }
}

class DataResponse extends Message {
    use ReflectiveMessageSerialization;  // Use trait for automatic serialization

    public function __construct(
        public readonly array $results,
        public readonly int $count,
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }
}

// Make request with response handler
$request = new DataRequest('SELECT * FROM users', ['active']);
$request->then(function(DataResponse $response) use ($region) {
    echo "Got " . $response->count . " results\n";
    $region->trigger(new ProcessComplete());
});

// Dispatch request
$region->trigger($request);

// Elsewhere, create correlated response using createResponse()
$responseData = ['results' => $actualResults, 'count' => count($actualResults)];
$response = $request->createResponse(DataResponse::class, $responseData);
$region->trigger($response);
```

### Example 2: State Machine using Messaging

```php
$region = (new RegionBuilder())
    ->enableFeatures(
        new SubscriptionFeature(),
        new MessageFeature(),
        new TransitionsFeature()
    )
    ->state('loading', [
        'entry' => function() {
            $request = new DataRequest('user.profile');
            $request->then(function($response) {
                // NOTE: $this->region assumes entry callbacks bind $this with region reference
                // Alternatively, capture region explicitly: use ($region) in then() closure
                $this->region->setContext([
                    'user' => $response->results[0] ?? null
                ]);
                // Trigger completion
                $this->region->trigger(new DataLoaded());
            });
            $this->region->trigger($request);
        }
    ])
    ->addTransition('loading', 'ready',
        fn($t) => $t instanceof DataLoaded
    )
    ->build();
```

### Example 3: Cross-Region Communication

```php
// Parent region coordinates child regions via messaging
$parent = (new RegionBuilder())
    ->enableFeatures(new MessageFeature())
    ->state('coordinating', [
        'entry' => function() {
            // Request work from child
            $childRequest = new WorkRequest('process_data');
            $childRequest->then(function($response) {
                echo "Child completed: " . $response->result;
            });
            $this->region->trigger($childRequest);
        }
    ])
    ->build();

class WorkRequest extends Message {
    use ReflectiveMessageSerialization;  // Auto-serialization for simple message

    public function __construct(
        public readonly string $task,
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }
}

class WorkResponse extends Message {
    use ReflectiveMessageSerialization;  // Auto-serialization for simple message

    public function __construct(
        public readonly string $result,
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }
}

// Child region responds
$child = (new RegionBuilder())
    ->enableFeatures(new MessageFeature())
    ->on(function(WorkRequest $req) {  // Listener for work requests
        // Do work...
        $result = $this->processData($req->task);

        // Send response with same correlation ID (array payload required)
        $response = $req->createResponse(WorkResponse::class, ['result' => $result]);
        $this->region->trigger($response);
    })
    ->build();

$parent->connect($child);
$parent->trigger(new StartCoordination());
```

### Example 4: Complex Serialization with Custom Logic

```php
class ComplexRequest extends Message {
    public function __construct(
        public readonly \DateTime $date,
        public readonly array $items,
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }

    public function jsonSerialize(): mixed {
        return [
            'correlationId' => $this->correlationId,
            'type' => static::class,
            'data' => [
                'date' => $this->date->format('Y-m-d H:i:s'),  // Custom date format
                'items' => array_map(fn($item) => $item->toArray(), $this->items)  // Custom item serialization
            ]
        ];
    }

    protected static function fromData(mixed $data, ?string $correlationId): static {
        return new self(
            date: new \DateTime($data['date']),  // Custom date parsing
            items: array_map(fn($item) => Item::fromArray($item), $data['items']),  // Custom item parsing
            correlationId: $correlationId
        );
    }
}
```

---

## Key Technical Design Decisions

### ✅ UUID-Based Correlation
- **Each Message generates unique UUID on instantiation**
- **Correlation ID links request-response pairs**
- **Long enough (UUID4) to prevent collisions**
- **Matches ROADMAP.md requirement for "abstract Message object that transparently creates a UUID"**

### ✅ Promise-Like then() API
- **Fluent interface for response handling**
- **Multiple handlers supported per message**
- **Called synchronously when response arrives**
- **Matches ROADMAP.md "promise-like then() method"**

### ✅ Manual Serialization with Optional Reflection Trait
- **Manual serialization preferred** - Subclasses implement their own logic
- **Optional reflection trait** - Zero boilerplate for simple cases
- **Full JSON compliance** - JsonSerializable implementation required
- **Bidirectional support** - Both jsonSerialize() and fromJson() methods

### ✅ Type-Safe Response Creation
- **createResponse() validates response class extends Message**
- **Uses fromData() method for reconstruction**
- **Injects correlation ID automatically**
- **Template type safety**

### ✅ Builds on SubscriptionFeature
- **Uses existing subscription infrastructure**
- **Adds Message-specific filtering and correlation**
- **Temporary subscriptions auto-cleaned**
- **Leverages Region parameter for event source identification**

### ✅ Action Chain Integration
- **MessageFeature hooks DispatchAction chain**
- **Scans for Message payloads**
- **Sets up correlation-based subscriptions**
- **Delivers responses when matching correlation detected**

### ✅ Memory Management
- **Temporary subscriptions deregistered after first delivery**
- **Correlation mappings cleaned up**
- **Prevents subscription buildup**

---

## Validation Plan

### Unit Tests per Spec (Red-Green-Refactor)

1. **Message class behavior**
   - UUID generation and correlation
   - then() handler registration
   - deliverResponse() delivery logic

2. **MessageFeature integration**
   - Middleware registration
   - Action chain interception
   - Temporary subscription setup

3. **Correlation delivery**
   - Matching correlation IDs
   - Response delivery to handlers
   - Subscription cleanup

4. **JSON serialization**
   - Manual serialization implementation
   - Reflection trait automation
   - fromJson() reconstruction

5. **Response creation**
   - Type-safe createResponse()
   - Correlation ID injection
   - Error handling for invalid types

### Integration Tests

1. **End-to-end messaging flow**
2. **Cross-region communication**
3. **Multiple concurrent messages**
4. **Memory leak prevention**
5. **JSON round-trip serialization**

### Validation Commands

```bash
# Run all message feature specs
ddev exec machines/middleware-test-runner/run.sh --spec=specs/features/message.yaml

# Enhanced subscription specs
ddev exec machines/middleware-test-runner/run.sh --spec=specs/features/subscription.yaml

# Full regression
ddev atlas

# Quality checks
ddev exec composer quality
```

---

## Dependencies & Order

### Feature Dependencies:

```
ExtendedState [optional for context]
  ↓
SubscriptionFeature [required for event filtering]
  ↓
MessageFeature [builds messaging on subscriptions]
```

### Why This Order Matters:

1. **ExtendedState** (if needed) - provides context data structure
2. **SubscriptionFeature** - provides type-filtered global listeners
3. **MessageFeature** - adds correlation logic on top of subscriptions

**Critical**: MessageFeature *requires* SubscriptionFeature for type-safe message delivery.

---

## Comparison: MessageFeature vs Direct Subscription

| Approach | Pros | Cons |
|----------|------|------|
| **Direct Subscription** | Simple, flexible, global listeners | Manual correlation, cleanup, no promise-like API |
| **MessageFeature** | Correlated req/res, promise-like, auto-cleanup | Feature dependency, more complex setup |

**MessageFeature is the "opinionated framework" approach** - provides standard patterns for common messaging needs while building on core subscription primitives.

---

## Next Steps

1. Review and approve MessageFeature specification proposal
2. Create concrete Message subclasses for example use cases
3. Implement core Message class and MessageFeature
4. Enhance SubscriptionFeature for correlation optimization
5. Add comprehensive tests and integration examples
6. Validate with existing test suite

---

## Design Decisions

### 1. Array-Only Payload Format ✅

**Decision**: All `createResponse()` payloads and `fromData()` implementations use **arrays exclusively**.

**Rationale**:
- Type-safe and predictable
- Aligns with JSON serialization format
- Consistent across all Message implementations
- `ReflectiveMessageSerialization` trait enforces this with exception

**Impact**:
- Manual `fromData()` implementations expect array `$data` parameter
- Trait throws `InvalidArgumentException` if non-array passed
- All examples updated to use array syntax

**Example**:
```php
// ✅ CORRECT
$response = $request->createResponse(DataResponse::class, [
    'results' => $data,
    'count' => count($data)
]);

// ❌ WRONG - Will throw exception with ReflectiveMessageSerialization
$response = $request->createResponse(DataResponse::class, (object)['result' => $data]);
```

---

### 2. First-Response-Wins Pattern ✅

**Decision**: Message subscriptions deliver the **first matching response** and immediately cleanup.

**Rationale**:
- Simple request-reply semantics
- Matches standard messaging patterns
- Prevents subscription buildup
- No complex timeout infrastructure needed

**Behavior**:
1. `then()` handler called when first matching correlation ID arrives
2. Subscription automatically removed after delivery
3. Subsequent responses with same correlation ID ignored

**Future Considerations**:
- Multi-response patterns (pub-sub, broadcast) deferred to future enhancement
- Could add `thenAll()` or similar API in v2 if needed

---

### 3. No Region Scope Filtering ✅

**Decision**: Responses matched by **correlation ID only**, not filtered by region.

**Rationale**:
- UUID collision astronomically unlikely
- Simpler implementation
- Enables flexible cross-region messaging patterns
- YAGNI - no current use case for region filtering

**Implication**:
- Any region can respond to any request with matching correlation ID
- Useful for cross-region communication patterns
- Can add region scoping in future if specific need identified

---

### 4. Timeout Mechanism Deferred ⏭️

**Decision**: Timeout/cleanup for lost responses **not included in v1**.

**Known Limitation**: If a response never arrives, the subscription persists indefinitely.

**Mitigation Strategies** (for users):
- Design systems with reliable response patterns
- Use long-running processes with known lifecycles
- Implement application-level cleanup if needed

**Future Enhancement**: Consider adding in v2:
- Global timeout configuration in `MessageFeature` constructor
- Per-message timeout via `timeout()` method
- Timer-based subscription cleanup

**Documentation**: This limitation will be clearly documented in README and feature docs.

---

### 5. Manual Type Validation (Developer Choice) ✅

**Decision**: `fromData()` implementations should document expected structure; add validation **only when complexity justifies it**.

**Guidelines**:
- Simple messages: Let PHP throw natural errors (e.g., undefined array key)
- Complex messages: Add explicit validation for better error messages
- Document expected structure in PHPDoc

**Example - Simple (no validation needed)**:
```php
/**
 * @param array{query: string, filters: array} $data
 */
protected static function fromData(mixed $data, ?string $correlationId): static {
    return new self(
        query: $data['query'] ?? '',
        filters: $data['filters'] ?? [],
        correlationId: $correlationId
    );
}
```

**Example - Complex (validation recommended)**:
```php
protected static function fromData(mixed $data, ?string $correlationId): static {
    if (!is_array($data) || !isset($data['date'], $data['items'])) {
        throw new \InvalidArgumentException('Invalid ComplexRequest data structure');
    }

    return new self(
        date: new \DateTime($data['date']),
        items: array_map(fn($item) => Item::fromArray($item), $data['items']),
        correlationId: $correlationId
    );
}
```

---

## Known Limitations (v1)

### Memory Management for Lost Responses

**Limitation**: Subscriptions created by `MessageFeature` persist until a matching response arrives. If a response is lost or never sent, the subscription remains indefinitely.

**Impact**: Potential memory leak in systems with high message throughput and unreliable response patterns.

**Workarounds**:
1. Design request-response flows with guaranteed responses
2. Use finite state machines with timeout states
3. Implement application-level cleanup during region teardown

**Planned Enhancement**: v2 will add configurable timeout mechanism

---

**End of MessageFeature Proposal**