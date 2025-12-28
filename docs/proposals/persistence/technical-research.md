# Persistence Layer - Technical Research & Patterns

**Date**: 2025-12-28
**Related**: persistence-layer.md (main proposal)

---

## Research Summary

This document captures the technical patterns and mechanisms discovered in the codebase that inform the Persistence Layer design.

---

## 1. Message Serialization Pattern

### Discovery: src/Feature/Message/Message.php

**Key Pattern**: Messages implement `JsonSerializable` with bidirectional marshalling:

```php
abstract class Message implements \JsonSerializable
{
    // Serialize to JSON-compatible structure
    abstract public function jsonSerialize(): mixed;

    // Deserialize from data + correlation ID
    abstract protected static function fromData(mixed $data, ?string $correlationId): static;

    // Reconstruct from full JSON array
    public static function fromJson(array $data, ?string $fqcn = null): static
    {
        $fqcn = $fqcn ?? ($data['type'] ?? null);

        if ($fqcn && is_subclass_of($fqcn, self::class)) {
            return $fqcn::fromData($data['data'] ?? null, $data['correlationId'] ?? null);
        }

        // Fallback to anonymous class
        return self::createAnonymousMessage($data);
    }
}
```

**Standard Format**:
```json
{
    "type": "Fully\\Qualified\\ClassName",
    "correlationId": "uuid-string",
    "data": {
        // Message-specific payload
    }
}
```

**Application to Persistence**:
- ✅ Use same pattern for serializing Region events in `dispatched` queue
- ✅ Store FQCN for class-based deserialization
- ✅ Support anonymous messages for generic events
- ✅ Preserve correlation IDs for message chains

**Implementation Note**:
```php
// In RegionSerializer::serializeDispatchedQueue()
private function serializeDispatchedQueue(array $dispatched): array
{
    return array_map(function(object $event) {
        if ($event instanceof Message) {
            // Use Message's built-in serialization
            return [
                'type' => get_class($event),
                'correlationId' => $event->correlationId(),
                'data' => $event->jsonSerialize()
            ];
        }

        if ($event instanceof \JsonSerializable) {
            return [
                'type' => get_class($event),
                'data' => $event->jsonSerialize()
            ];
        }

        // Generic object - serialize public properties
        return [
            'type' => get_class($event),
            'data' => get_object_vars($event)
        ];
    }, $dispatched);
}
```

---

## 2. ReflectiveMessageSerialization Trait

### Discovery: src/Feature/Message/ReflectiveMessageSerialization.php

**Key Pattern**: Automatic serialization via reflection for simple cases:

```php
trait ReflectiveMessageSerialization
{
    public function jsonSerialize(): mixed
    {
        $reflection = new \ReflectionClass($this);
        $properties = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getName() === 'correlationId') {
                continue; // Handled separately
            }
            $properties[$property->getName()] = $property->getValue($this);
        }

        return [
            'correlationId' => $this->correlationId,
            'type' => static::class,
            'data' => $properties
        ];
    }

    protected static function fromData(mixed $data, ?string $correlationId): static
    {
        $reflection = new \ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();

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

**Application to Persistence**:
- ✅ Provide `ReflectiveRegionSerialization` trait for opt-in automatic serialization
- ✅ Use reflection to access private Region properties during snapshot
- ✅ Map constructor parameters by name during restoration
- ⚠️ Handle readonly properties correctly (constructor-only assignment)

**Performance Note**: Reflection is expensive. Consider caching reflected metadata:

```php
class SerializationMetadata
{
    private static array $cache = [];

    public static function getProperties(string $class): array
    {
        if (!isset(self::$cache[$class])) {
            $reflection = new \ReflectionClass($class);
            self::$cache[$class] = $reflection->getProperties();
        }
        return self::$cache[$class];
    }
}
```

---

## 3. ExtendedState Context Storage via Meta Chain

### Discovery: src/Feature/ExtendedState/ExtendedState.php

**Key Pattern**: Context data stored in Meta chain using MetaType identifier:

```php
// Get operation
$getChain->link(
    function (Params\Get $get, callable $next) use ($meta) {
        $metaParams = new Params\Meta($get->region, ContextMetaType::get());
        $data = $meta->call($metaParams);  // Returns Mesh (ArrayAccess)

        if (isset($data[$get->key])) {
            return $data[$get->key];
        }

        return $next($get);  // Fall through to next middleware
    }
);

// Set operation
$setChain->link(
    function (Params\Set $set, callable $next) use ($meta) {
        $metaParams = new Params\Meta($set->region, ContextMetaType::get());
        $data = $meta->call($metaParams);  // Get Mesh for this region
        $data[$set->key] = $set->value;     // Store in Mesh

        return $next($set);
    }
);
```

**Storage Structure**:
```
Meta Chain (per Region)
    ↓
ContextMetaType → Mesh (ArrayAccess)
    ↓
['key1' => value1, 'key2' => value2, ...]
```

**Application to Persistence**:

```php
// ContextSerializer implementation
class ContextSerializer
{
    public function serialize(Region $region, Chains\Meta $metaChain): array
    {
        $metaParams = new Params\Meta($region, ContextMetaType::get());
        $contextMesh = $metaChain->call($metaParams);

        // Mesh implements Traversable
        $serialized = [];
        foreach ($contextMesh as $key => $value) {
            if ($this->policy->shouldExclude($key)) {
                continue;
            }

            $serialized[$key] = $this->serializeValue($value);
        }

        return $serialized;
    }

    public function restore(Region $region, array $contextData, Chains\Meta $metaChain): void
    {
        $metaParams = new Params\Meta($region, ContextMetaType::get());
        $contextMesh = $metaChain->call($metaParams);

        foreach ($contextData as $key => $value) {
            $contextMesh[$key] = $this->deserializeValue($value);
        }
    }
}
```

**Critical Insight**: Must restore Meta chain data BEFORE triggering any events, since callbacks may access `$this->get()`.

---

## 4. Meta Chain Inheritance (Parent-Child Regions)

### Discovery: src/Chains/Meta.php:76-116

**Key Pattern**: Child Meshes extend parent Meshes for context inheritance:

```php
if ($parentRegion = $connectedRegions->call($regionParams)) {
    if (!$metaData->contains($parentRegion) || !$metaData->contains($metaParams->region)) {
        $parentMetaParams = new Params\Meta($parentRegion, ContextMetaType::get());
        $parent = $first($parentMetaParams);
        $child = $next($metaParams);
        $parent->extendWith($child);  // Child inherits from parent
    }
}
```

**Behavior**:
- Child reads parent values via Mesh inheritance
- Child writes are LOCAL only
- Parent changes visible to child
- Child changes NOT visible to parent

**Application to Persistence**:

When serializing hierarchical Regions:

```php
private function serializeRegion(Region $region): array
{
    $children = $this->getConnectedChildren($region);

    return [
        'id' => spl_object_id($region),
        'state' => $this->serializeState($region),
        'context' => $this->serializeContext($region), // Local context only
        'children' => array_map(
            fn(Region $child) => $this->serializeRegion($child),
            $children
        ),
        'connections' => $this->serializeConnections($region, $children),
    ];
}

private function restoreRegion(array $data, ?Region $parent = null): Region
{
    $region = $this->buildRegionFromState($data['state']);

    // Restore local context
    $this->restoreContext($region, $data['context']);

    // Restore children and connections
    foreach ($data['children'] as $childData) {
        $child = $this->restoreRegion($childData, parent: $region);

        // Restore connection with flags
        $connectionData = $this->findConnection($data['connections'], $childData['id']);
        $region->connect($child, $connectionData['flags']);
    }

    return $region;
}
```

**Critical Insight**: Connection flags (RECEIVE_META, FORWARD_EVENTS, etc.) must be serialized and restored to maintain parent-child relationships.

---

## 5. Region Private State Access

### Discovery: src/Region.php

**Properties to Serialize**:

```php
class Region
{
    private string $currentState;              // ✅ Required
    private array $dispatched = [];            // ✅ Required (event queue)
    private bool $initialStateEntered = false; // ✅ Required

    // Constructor dependencies (reconstructed during restore)
    private readonly Events $events;           // ⚠️ Complex (state definitions)
    private readonly string $final;            // ✅ Required
    private readonly Chains\DispatchAction $actionChain;   // ⚠️ Has middleware
    private readonly Chains\DoTransition $transitionChain; // ⚠️ Has middleware
    private readonly Chains\Path $path;        // ⚠️ Readonly chain
    public readonly Chains\Notification $notificationChain; // ⚠️ Has listeners
}
```

**Access Strategy**:

```php
class RegionSerializer
{
    private function extractPrivateProperty(Region $region, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($region);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($region);
    }

    private function setPrivateProperty(Region $region, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionClass($region);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($region, $value);
    }

    public function serialize(Region $region): array
    {
        return [
            'currentState' => $this->extractPrivateProperty($region, 'currentState'),
            'dispatched' => $this->extractPrivateProperty($region, 'dispatched'),
            'initialStateEntered' => $this->extractPrivateProperty($region, 'initialStateEntered'),
            'final' => $this->extractPrivateProperty($region, 'final'),
            // Events, chains require separate serialization strategy
        ];
    }
}
```

**Challenge**: `readonly` properties cannot be set after construction.

**Solution**: Use `ReflectionProperty::setValue()` on uninitialized object:

```php
private function restoreRegion(array $data): Region
{
    $reflection = new \ReflectionClass(Region::class);

    // Create uninitialized instance (no constructor)
    $region = $reflection->newInstanceWithoutConstructor();

    // Set private readonly properties via reflection
    foreach ($data as $propertyName => $value) {
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($region, $value);
    }

    return $region;
}
```

**⚠️ Warning**: Using `newInstanceWithoutConstructor()` bypasses initialization logic. Must ensure all invariants are manually restored.

---

## 6. Runtime State Management

### Discovery: src/Runtime.php

**Properties to Serialize**:

```php
abstract class Runtime implements \IteratorAggregate
{
    private int $iteration = 0;              // ✅ Simple
    private bool $complete = false;          // ✅ Simple
    private bool $completionFired = false;   // ✅ Simple

    private readonly Region $region;         // ⚠️ Readonly - separate serialization
    private readonly RuntimeConfig $config;  // ⚠️ Contains closures
}
```

**RuntimeConfig Serialization Challenge**:

```php
class RuntimeConfig
{
    public function __construct(
        public readonly int $maxIterations = 0,
        public readonly ?\Closure $onIteration = null,  // ⚠️ Closure
        public readonly ?\Closure $onComplete = null,   // ⚠️ Closure
        public readonly ?\Closure $triggerFactory = null // ⚠️ Closure
    ) {}
}
```

**Strategy**: Skip closures, warn user, support reconstruction:

```php
class RuntimeSerializer
{
    public function serializeConfig(RuntimeConfig $config): array
    {
        $serialized = [
            'maxIterations' => $config->maxIterations,
            'onIteration' => null,
            'onComplete' => null,
            'triggerFactory' => null,
        ];

        // Warn if closures present
        if ($config->onIteration !== null
            || $config->onComplete !== null
            || $config->triggerFactory !== null
        ) {
            trigger_error(
                'RuntimeConfig closures cannot be serialized. ' .
                'Callbacks will be null after restore. ' .
                'Consider using named functions or reconstructing config manually.',
                E_USER_WARNING
            );
        }

        return $serialized;
    }

    public function restoreConfig(array $data): RuntimeConfig
    {
        return new RuntimeConfig(
            maxIterations: $data['maxIterations'] ?? 0,
            onIteration: null,  // User must re-attach if needed
            onComplete: null,   // User must re-attach if needed
            triggerFactory: null // User must re-attach if needed
        );
    }
}

// Alternative: Allow user to provide config during restore
public function restore(string $snapshot, ?RuntimeConfig $config = null): Runtime
{
    $data = $this->deserialize($snapshot);
    $region = $this->restoreRegion($data['region']);

    $runtimeConfig = $config ?? $this->restoreConfig($data['runtime']['config']);

    return new StandardRuntime($region, $runtimeConfig);
}
```

---

## 7. JsonSchemaFeature Pattern (Extensibility)

### Discovery: src/Feature/JsonSchema/JsonSchemaFeature.php

**Key Pattern**: Features extend loader schema to recognize new YAML keys:

```php
$schema?->link(function (SchemaContext $context, callable $next) {
    $contextSchema = $context->getCustomSchema('context');
    assert($contextSchema instanceof Structure);

    // Extend existing schema
    $contextSchema = $contextSchema->extend([
        'schema' => Expect::listOf(
            Expect::structure([
                'name' => Expect::string(),
                'type' => Expect::string(),
                // ...
            ])
        ),
    ]);

    $context->addCustomSchema('context', $contextSchema);

    // Update region schema reference
    $context->region = $context->region->extend([
        'context' => $contextSchema,
    ]);

    return $next($context);
});
```

**Application to Persistence**:

Enable YAML-based persistence configuration:

```yaml
# machine.yml
states:
  idle: {}
  processing: {}
  done: { type: final }

context:
  data: []
  counter: 0

  # Persistence configuration
  persistence:
    enabled: true
    backend: json
    exclude:
      - temp_data
      - session
    snapshot_interval: 1000  # Snapshot every 1000 iterations
```

**Implementation**:

```php
class PersistenceFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply()->use(
            function (
                EnhanceRegionBuilder $enhanceRegionBuilder,
                ?LoaderChains\Schema $schema
            ) {
                // Extend context schema to recognize 'persistence' section
                $schema?->link(function (SchemaContext $context, callable $next) {
                    $contextSchema = $context->getCustomSchema('context');

                    $contextSchema = $contextSchema->extend([
                        'persistence' => Expect::structure([
                            'enabled' => Expect::bool(true),
                            'backend' => Expect::string('json'),
                            'exclude' => Expect::listOf('string'),
                            'snapshot_interval' => Expect::int()->nullable(),
                        ])->nullable(),
                    ]);

                    $context->addCustomSchema('context', $contextSchema);
                    $context->region = $context->region->extend([
                        'context' => $contextSchema,
                    ]);

                    return $next($context);
                });

                // Read config and configure PersistenceManager
                $enhanceRegionBuilder?->link(function (BuildParams $context, callable $next) {
                    $loaderConfig = $context->config(LoaderConfig::class);

                    if ($loaderConfig->hasContext('persistence')) {
                        $persistenceConfig = $loaderConfig->context('persistence');

                        // Configure manager based on YAML
                        $policy = new SerializationPolicy();
                        foreach ($persistenceConfig['exclude'] ?? [] as $key) {
                            $policy->exclude($key);
                        }

                        // Inject configured policy
                        $this->policy = $policy;
                    }

                    return $next($context);
                });
            }
        );
    }
}
```

---

## 8. Mesh ArrayAccess Pattern

### Discovery: src/Middleware/Mesh.php

**Key Pattern**: Mesh provides hierarchical data storage with inheritance:

```php
class Mesh implements \ArrayAccess, \Traversable
{
    private array $data = [];
    private ?Mesh $parent = null;

    public function extendWith(Mesh $child): void
    {
        $child->parent = $this;
    }

    public function offsetGet(mixed $offset): mixed
    {
        // Check local data first
        if (isset($this->data[$offset])) {
            return $this->data[$offset];
        }

        // Fall back to parent
        return $this->parent?->offsetGet($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Writes are always local
        $this->data[$offset] = $value;
    }
}
```

**Application to Persistence**:

When serializing Mesh data:

```php
class MeshSerializer
{
    public function serialize(Mesh $mesh): array
    {
        // Only serialize local data, not inherited
        $reflection = new \ReflectionClass($mesh);
        $dataProperty = $reflection->getProperty('data');
        $dataProperty->setAccessible(true);

        return $dataProperty->getValue($mesh);
    }

    public function restore(Mesh $mesh, array $data): void
    {
        foreach ($data as $key => $value) {
            $mesh[$key] = $value;
        }
    }
}
```

**Critical Insight**: Only serialize local Mesh data, not inherited values. Parent-child relationships are reconstructed via `connect()` calls.

---

## 9. Recommended Serialization Libraries

Based on codebase complexity and requirements:

### Option 1: Pure JSON (Recommended for MVP)

**Pros**:
- No dependencies
- Human-readable
- Debuggable
- Cross-language compatible

**Cons**:
- Verbose (large snapshots)
- No native closure support
- Type information lost

**Use Case**: Development, debugging, simple workflows

### Option 2: igbinary (Binary Efficiency)

```php
class IgbinaryBackend implements PersistenceBackend
{
    public function serialize(array $snapshot): string
    {
        return igbinary_serialize($snapshot);
    }

    public function deserialize(string $data): array
    {
        return igbinary_unserialize($data);
    }
}
```

**Pros**:
- ~50% smaller than JSON
- Faster serialization
- Built into many PHP distributions

**Cons**:
- Not human-readable
- Requires extension

**Use Case**: Production snapshots, high-frequency captures

### Option 3: MessagePack (Cross-Platform Binary)

```php
class MessagePackBackend implements PersistenceBackend
{
    public function serialize(array $snapshot): string
    {
        return msgpack_pack($snapshot);
    }

    public function deserialize(string $data): array
    {
        return msgpack_unpack($data);
    }
}
```

**Pros**:
- Compact binary format
- Cross-language (Python, Ruby, JS)
- Faster than JSON

**Cons**:
- Requires extension/library
- Not human-readable

**Use Case**: Distributed systems, microservices

### Option 4: Opis/Closure (For Closures)

```php
use Opis\Closure\SerializableClosure;

class ClosureAwareBackend implements PersistenceBackend
{
    public function serialize(array $snapshot): string
    {
        // Wrap closures in SerializableClosure
        $wrapped = $this->wrapClosures($snapshot);
        return serialize($wrapped);
    }

    private function wrapClosures(mixed $value): mixed
    {
        if ($value instanceof \Closure) {
            return new SerializableClosure($value);
        }

        if (is_array($value)) {
            return array_map([$this, 'wrapClosures'], $value);
        }

        return $value;
    }
}
```

**Pros**:
- Serializes closures
- Handles use() bindings

**Cons**:
- Fragile (relies on code availability)
- Security risk (code injection)
- Not suitable for long-term storage

**Use Case**: Short-term snapshots, same-codebase restoration

---

## 10. Testing Strategy

### Unit Tests

```php
// Test basic serialization round-trip
public function testSerializeDeserializeBasicRegion(): void
{
    $region = (new RegionBuilder())
        ->setStates('idle', 'processing', 'done')
        ->build();

    $serializer = new RegionSerializer();
    $data = $serializer->serialize($region);
    $restored = $serializer->deserialize($data);

    $this->assertEquals('idle', $restored->getCurrentState());
}

// Test context preservation
public function testSerializePreservesContext(): void
{
    $region = (new RegionBuilder())
        ->enableFeatures(new ExtendedState())
        ->setStates('idle', 'done')
        ->onEnter('idle', fn($t) => $this->set('key', 'value'))
        ->build();

    $region->trigger(new \stdClass());

    $persistence = new PersistenceManager(new JsonBackend());
    $runtime = new StandardRuntime($region);

    $snapshot = $persistence->capture($runtime);
    $restored = $persistence->restore($snapshot);

    // Verify context preserved
    $this->assertContextEquals($restored->getRegion(), 'key', 'value');
}
```

### Integration Tests

```php
// Test pause/resume workflow
public function testPauseResumeWorkflow(): void
{
    $region = Holon::fromYaml('workflow.yml')->bootstrap();
    $runtime = new StandardRuntime($region);

    // Run 10 steps
    $runtime->run(steps: 10);
    $state1 = $runtime->getRegion()->getCurrentState();

    // Capture and restore
    $persistence = new PersistenceManager(new JsonBackend());
    $snapshot = $persistence->capture($runtime);
    $restored = $persistence->restore($snapshot);

    // Should resume from same state
    $this->assertEquals($state1, $restored->getRegion()->getCurrentState());

    // Continue execution
    $restored->run(steps: 10);
}

// Test hierarchical regions
public function testSerializeParentChildRegions(): void
{
    $parent = (new RegionBuilder())
        ->enableFeatures(new ExtendedState())
        ->setStates('idle', 'done')
        ->build();

    $child = (new RegionBuilder())
        ->enableFeatures(new ExtendedState())
        ->setStates('working', 'finished')
        ->build();

    $parent->connect($child, Connection::RECEIVE_META | Connection::FORWARD_EVENTS);

    $persistence = new PersistenceManager(new JsonBackend());
    $runtime = new StandardRuntime($parent);

    $snapshot = $persistence->capture($runtime);
    $restored = $persistence->restore($snapshot);

    // Verify parent-child relationship preserved
    $this->assertConnected($restored->getRegion(), $child);
}
```

### Performance Tests

```php
// Test snapshot size
public function testSnapshotSize(): void
{
    $region = $this->createLargeWorkflow(100); // 100 states
    $runtime = new StandardRuntime($region);

    $persistence = new PersistenceManager(new JsonBackend());
    $snapshot = $persistence->capture($runtime);

    // Assert reasonable size (< 1MB for 100-state machine)
    $this->assertLessThan(1024 * 1024, strlen($snapshot));
}

// Test serialization performance
public function testSerializationPerformance(): void
{
    $region = $this->createComplexWorkflow();
    $runtime = new StandardRuntime($region);

    $persistence = new PersistenceManager(new JsonBackend());

    $start = microtime(true);
    for ($i = 0; $i < 1000; $i++) {
        $snapshot = $persistence->capture($runtime);
    }
    $elapsed = microtime(true) - $start;

    // Assert < 1ms per snapshot
    $this->assertLessThan(1.0, $elapsed);
}
```

---

## 11. Migration Path from Existing Codebase

### Step 1: Add Serialization Support to Messages

All existing Message subclasses should implement `jsonSerialize()` and `fromData()`:

```php
// Example: Update existing message
class CustomMessage extends Message
{
    public function __construct(
        public readonly string $id,
        public readonly array $data,
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }

    // Add these methods:
    public function jsonSerialize(): mixed
    {
        return [
            'correlationId' => $this->correlationId,
            'type' => static::class,
            'data' => [
                'id' => $this->id,
                'data' => $this->data
            ]
        ];
    }

    protected static function fromData(mixed $data, ?string $correlationId): static
    {
        return new self(
            id: $data['id'],
            data: $data['data'],
            correlationId: $correlationId
        );
    }
}
```

### Step 2: Audit Context for Serialization

Identify non-serializable types in context:

```bash
# Search for common non-serializable types
grep -r "set.*resource" machines/
grep -r "set.*Closure" machines/
grep -r "set.*PDO" machines/
```

Add warnings or policies:

```php
// In PersistenceManager
private function validateContext(array $context): void
{
    foreach ($context as $key => $value) {
        if (is_resource($value)) {
            trigger_error(
                "Context key '{$key}' contains resource. " .
                "Resources cannot be serialized. Consider excluding via policy.",
                E_USER_WARNING
            );
        }

        if ($value instanceof \PDO) {
            trigger_error(
                "Context key '{$key}' contains database connection. " .
                "Connections cannot be serialized. Exclude via policy.",
                E_USER_WARNING
            );
        }
    }
}
```

### Step 3: Update Existing Machines

Add persistence configuration to YAML:

```yaml
# machines/example/machine.yml
context:
  persistence:
    enabled: true
    exclude:
      - db_connection
      - temp_cache
```

---

## Summary

This research reveals several strong patterns in the codebase that directly inform the Persistence Layer design:

✅ **Message serialization** provides a proven bidirectional marshalling pattern
✅ **ReflectiveMessageSerialization** offers automatic serialization via reflection
✅ **ExtendedState + Meta chain** shows how to access and serialize context data
✅ **Mesh hierarchy** demonstrates parent-child data inheritance
✅ **JsonSchemaFeature** provides extensibility pattern for YAML configuration
✅ **Region private state** requires reflection-based access for serialization

**Recommended Implementation Priority**:

1. Start with JSON backend (simple, debuggable)
2. Use Message serialization pattern for events
3. Use ReflectiveMessageSerialization approach for Region state
4. Serialize Meta chain data via ContextMetaType
5. Add SerializationPolicy for opt-out mechanism
6. Extend with binary backends (igbinary/msgpack) for production
7. Consider Opis/Closure as optional enhancement, not core dependency

---

**Last Updated**: 2025-12-28
