# Persistence Layer - API Reference

**Status**: Draft
**Created**: 2025-12-28
**Related**: [README.md](./README.md)

## Table of Contents

1. [PersistenceFeature](#persistencefeature)
2. [PersistenceManager](#persistencemanager)
3. [PersistenceBackend](#persistencebackend)
4. [SerializationPolicy](#serializationpolicy)
5. [Built-in Backends](#built-in-backends)
6. [Exceptions](#exceptions)
7. [Configuration](#configuration)

---

## PersistenceFeature

**Namespace**: `Noem\State\Feature\Persistence`

**Implements**: `Feature`

### Synopsis

```php
class PersistenceFeature implements Feature
{
    public function __construct(
        PersistenceBackend $backend = new JsonBackend(),
        SerializationPolicy $policy = new SerializationPolicy()
    );

    public function __invoke(ChainMail $chainMail): void;
}
```

### Constructor Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$backend` | `PersistenceBackend` | `JsonBackend` | Serialization backend |
| `$policy` | `SerializationPolicy` | `new SerializationPolicy()` | Opt-in/out policy |

### Usage

```php
use Noem\State\Feature\Persistence\PersistenceFeature;
use Noem\State\Feature\Persistence\Backend\JsonBackend;
use Noem\State\Feature\Persistence\SerializationPolicy;

// Basic usage (JSON backend)
$region = (new RegionBuilder())
    ->enableFeatures(
        new ExtendedState(),
        new PersistenceFeature()
    )
    ->setStates('idle', 'done')
    ->build();

// Custom backend and policy
$policy = (new SerializationPolicy())
    ->exclude('password', 'api_key')
    ->skipClosures(true);

$region = (new RegionBuilder())
    ->enableFeatures(
        new ExtendedState(),
        new PersistenceFeature(
            backend: new DatabaseBackend($pdo),
            policy: $policy
        )
    )
    ->build();
```

### Methods

#### `__invoke(ChainMail $chainMail): void`

Registers the persistence feature with the ChainMail container.

**Internal**: Supplies `PersistenceManager` to container and registers hooks.

---

## PersistenceManager

**Namespace**: `Noem\State\Feature\Persistence`

### Synopsis

```php
class PersistenceManager
{
    public function __construct(
        PersistenceBackend $backend,
        SerializationPolicy $policy,
        ChainMail $chainMail
    );

    public function capture(Runtime $runtime): string;
    public function restore(string $data, ?RuntimeConfig $config = null): Runtime;
    public function snapshot(Runtime $runtime): string;
    public function validate(string $data): bool;
}
```

### Constructor

Typically managed by PersistenceFeature. Not usually constructed directly.

---

### Methods

#### `capture(Runtime $runtime): string`

Serializes a Runtime instance to a snapshot string.

**Parameters**:
- `$runtime` (`Runtime`): The runtime to serialize

**Returns**: `string` - Serialized snapshot (format depends on backend)

**Throws**:
- `SerializationException` - If serialization fails
- `SecurityException` - If snapshot contains disallowed content

**Example**:

```php
$runtime = new StandardRuntime($region);
$runtime->run(steps: 100);

$persistence = $runtime->getPersistenceManager();
$snapshot = $persistence->capture($runtime);

// Save to file
file_put_contents('workflow.snapshot', $snapshot);

// Or database
$db->execute(
    'INSERT INTO snapshots (id, data) VALUES (?, ?)',
    [$id, $snapshot]
);
```

---

#### `restore(string $data, ?RuntimeConfig $config = null): Runtime`

Deserializes a snapshot string back into a Runtime instance.

**Parameters**:
- `$data` (`string`): Serialized snapshot
- `$config` (`?RuntimeConfig`): Optional runtime config (overrides snapshot's config)

**Returns**: `Runtime` - Restored runtime instance

**Throws**:
- `SerializationException` - If deserialization fails
- `IncompatibleSnapshotException` - If snapshot version incompatible
- `ValidationException` - If snapshot validation fails

**Example**:

```php
// Load from file
$snapshot = file_get_contents('workflow.snapshot');

// Restore
$persistence = new PersistenceManager(new JsonBackend());
$runtime = $persistence->restore($snapshot);

// Continue execution
$runtime->run();

// With custom config
$config = new RuntimeConfig(maxIterations: 1000);
$runtime = $persistence->restore($snapshot, $config);
```

---

#### `snapshot(Runtime $runtime): string`

Alias for `capture()`. Creates a snapshot without stopping execution.

**Parameters**:
- `$runtime` (`Runtime`): The runtime to snapshot

**Returns**: `string` - Serialized snapshot

**Example**:

```php
// Periodic snapshots during execution
$runtime->run(steps: 1000);
$snapshot1 = $persistence->snapshot($runtime);

$runtime->run(steps: 1000);
$snapshot2 = $persistence->snapshot($runtime);

// Runtime continues unaffected
$runtime->run();
```

---

#### `validate(string $data): bool`

Validates a snapshot without fully deserializing it.

**Parameters**:
- `$data` (`string`): Serialized snapshot

**Returns**: `bool` - True if valid, false otherwise

**Example**:

```php
$snapshot = file_get_contents('workflow.snapshot');

if (!$persistence->validate($snapshot)) {
    throw new \RuntimeException('Invalid snapshot');
}

$runtime = $persistence->restore($snapshot);
```

---

## PersistenceBackend

**Namespace**: `Noem\State\Feature\Persistence`

**Type**: Interface

### Synopsis

```php
interface PersistenceBackend
{
    public function serialize(array $snapshot): string;
    public function deserialize(string $data): array;
    public function validate(string $data): bool;
}
```

### Methods

#### `serialize(array $snapshot): string`

Converts snapshot array to storage format.

**Parameters**:
- `$snapshot` (`array`): Normalized snapshot data

**Returns**: `string` - Serialized data (format-specific)

**Throws**: `SerializationException`

---

#### `deserialize(string $data): array`

Converts storage format back to snapshot array.

**Parameters**:
- `$data` (`string`): Serialized data

**Returns**: `array` - Normalized snapshot data

**Throws**: `SerializationException`

---

#### `validate(string $data): bool`

Validates serialized data without full deserialization.

**Parameters**:
- `$data` (`string`): Serialized data

**Returns**: `bool` - True if valid

---

## SerializationPolicy

**Namespace**: `Noem\State\Feature\Persistence`

### Synopsis

```php
class SerializationPolicy
{
    public function exclude(string ...$keys): self;
    public function registerSerializer(
        string $type,
        callable $serializer,
        callable $deserializer
    ): self;
    public function skipClosures(bool $skip = true): self;

    public function shouldExclude(string $key): bool;
    public function getSerializer(string $type): ?array;
    public function shouldSkipClosures(): bool;
}
```

### Methods

#### `exclude(string ...$keys): self`

Excludes specific context keys from serialization.

**Parameters**:
- `$keys` (`string...`): Keys to exclude

**Returns**: `self` (fluent)

**Example**:

```php
$policy = (new SerializationPolicy())
    ->exclude('password', 'api_key', 'session');
```

---

#### `registerSerializer(string $type, callable $serializer, callable $deserializer): self`

Registers custom serialization logic for a specific type.

**Parameters**:
- `$type` (`class-string`): Fully-qualified class name
- `$serializer` (`callable(T): array`): Serialization function
- `$deserializer` (`callable(array): T`): Deserialization function

**Returns**: `self` (fluent)

**Example**:

```php
$policy->registerSerializer(
    \DateTime::class,
    serialize: fn(\DateTime $dt) => $dt->format(\DateTime::ATOM),
    deserialize: fn(string $str) => new \DateTime($str)
);

$policy->registerSerializer(
    OrderId::class,
    serialize: fn(OrderId $id) => ['value' => $id->toString()],
    deserialize: fn(array $data) => OrderId::fromString($data['value'])
);
```

---

#### `skipClosures(bool $skip = true): self`

Configures whether closures should be skipped during serialization.

**Parameters**:
- `$skip` (`bool`): True to skip (default), false to attempt serialization

**Returns**: `self` (fluent)

**Example**:

```php
// Skip closures (safe, default)
$policy->skipClosures(true);

// Attempt closure serialization (requires opis/closure)
$policy->skipClosures(false);
```

---

#### `shouldExclude(string $key): bool`

Checks if a context key should be excluded.

**Parameters**:
- `$key` (`string`): Context key

**Returns**: `bool` - True if excluded

---

#### `getSerializer(string $type): ?array`

Gets custom serializer for a type if registered.

**Parameters**:
- `$type` (`class-string`): Type to look up

**Returns**: `?array` - `['serialize' => callable, 'deserialize' => callable]` or null

---

#### `shouldSkipClosures(): bool`

Checks if closures should be skipped.

**Returns**: `bool` - True if skipping closures

---

## Built-in Backends

### JsonBackend

**Namespace**: `Noem\State\Feature\Persistence\Backend`

**Implements**: `PersistenceBackend`

```php
class JsonBackend implements PersistenceBackend
{
    public function __construct(
        int $flags = JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );

    public function serialize(array $snapshot): string;
    public function deserialize(string $data): array;
    public function validate(string $data): bool;
}
```

**Features**:
- Human-readable JSON output
- Configurable formatting flags
- Standard PHP JSON encoding/decoding

**Example**:

```php
// Pretty-printed (development)
$backend = new JsonBackend(JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

// Compact (production)
$backend = new JsonBackend(JSON_THROW_ON_ERROR);

// Unescaped Unicode
$backend = new JsonBackend(
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
```

---

### DatabaseBackend

**Namespace**: `Noem\State\Feature\Persistence\Backend`

**Implements**: `PersistenceBackend`

```php
class DatabaseBackend implements PersistenceBackend
{
    public function __construct(
        \PDO $pdo,
        string $table = 'workflow_snapshots'
    );

    public function serialize(array $snapshot): string;
    public function deserialize(string $id): array;
    public function validate(string $id): bool;
}
```

**Features**:
- Stores snapshots in database table
- Returns snapshot ID instead of full data
- Automatic table creation (optional)

**Schema**:

```sql
CREATE TABLE workflow_snapshots (
    id VARCHAR(36) PRIMARY KEY,
    data MEDIUMBLOB NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    INDEX idx_created (created_at)
);
```

**Example**:

```php
$pdo = new PDO('mysql:host=localhost;dbname=app', 'user', 'pass');
$backend = new DatabaseBackend($pdo, 'snapshots');

$persistence = new PersistenceManager($backend, new SerializationPolicy());

// Serialize - returns UUID
$snapshotId = $persistence->capture($runtime);
echo "Saved as: {$snapshotId}";

// Restore - loads from database
$runtime = $persistence->restore($snapshotId);
```

---

### CompressedBackend

**Namespace**: `Noem\State\Feature\Persistence\Backend`

**Implements**: `PersistenceBackend`

```php
class CompressedBackend implements PersistenceBackend
{
    public function __construct(
        PersistenceBackend $inner,
        int $level = 6
    );

    public function serialize(array $snapshot): string;
    public function deserialize(string $data): array;
    public function validate(string $data): bool;
}
```

**Features**:
- Wraps another backend with gzip compression
- Configurable compression level (1-9)
- Transparent decompression

**Example**:

```php
// JSON + gzip (balanced)
$backend = new CompressedBackend(new JsonBackend(), level: 6);

// JSON + maximum compression
$backend = new CompressedBackend(new JsonBackend(), level: 9);

// Database + compression
$backend = new CompressedBackend(
    new DatabaseBackend($pdo),
    level: 6
);
```

---

### EncryptedBackend

**Namespace**: `Noem\State\Feature\Persistence\Backend`

**Implements**: `PersistenceBackend`

```php
class EncryptedBackend implements PersistenceBackend
{
    public function __construct(
        PersistenceBackend $inner,
        string $encryptionKey
    );

    public function serialize(array $snapshot): string;
    public function deserialize(string $data): array;
    public function validate(string $data): bool;
}
```

**Features**:
- Wraps another backend with libsodium encryption
- Authenticated encryption (prevents tampering)
- Random nonce per snapshot

**Example**:

```php
// Generate key (do this once, store securely)
$key = sodium_crypto_secretbox_keygen();

// Encrypted JSON backend
$backend = new EncryptedBackend(
    new JsonBackend(),
    $key
);

// Encrypted + compressed + database
$backend = new EncryptedBackend(
    new CompressedBackend(
        new DatabaseBackend($pdo)
    ),
    $key
);
```

---

## Exceptions

### SerializationException

**Namespace**: `Noem\State\Feature\Persistence\Exception`

**Extends**: `\RuntimeException`

Thrown when serialization or deserialization fails.

**Example**:

```php
try {
    $snapshot = $persistence->capture($runtime);
} catch (SerializationException $e) {
    error_log("Serialization failed: {$e->getMessage()}");
    throw $e;
}
```

---

### IncompatibleSnapshotException

**Namespace**: `Noem\State\Feature\Persistence\Exception`

**Extends**: `SerializationException`

Thrown when snapshot version is incompatible with current code.

**Example**:

```php
try {
    $runtime = $persistence->restore($snapshot);
} catch (IncompatibleSnapshotException $e) {
    error_log("Snapshot version mismatch: {$e->getMessage()}");
    // Try migration or reject
}
```

---

### SecurityException

**Namespace**: `Noem\State\Feature\Persistence\Exception`

**Extends**: `SerializationException`

Thrown when security validation fails.

**Example**:

```php
try {
    $runtime = $persistence->restore($untrustedSnapshot);
} catch (SecurityException $e) {
    error_log("Security violation: {$e->getMessage()}");
    // Log incident, alert admin
}
```

---

## Configuration

### YAML Configuration

When using PersistenceFeature with RegionLoader:

```yaml
context:
  # Application context
  order_id: null
  status: pending

  # Persistence configuration
  persistence:
    enabled: true
    backend: json
    exclude:
      - temp_data
      - cache
    snapshot_interval: 1000
```

**Schema**:

```php
// In PersistenceFeature::__invoke()
$schema?->link(function (SchemaContext $context, callable $next) {
    $contextSchema = $context->getCustomSchema('context');

    $contextSchema = $contextSchema->extend([
        'persistence' => Expect::structure([
            'enabled' => Expect::bool(true),
            'backend' => Expect::anyOf('json', 'database', 'redis')->default('json'),
            'exclude' => Expect::listOf('string')->default([]),
            'snapshot_interval' => Expect::int()->nullable(),
            'compression' => Expect::bool(false),
            'encryption_key' => Expect::string()->nullable(),
        ])->nullable(),
    ]);

    // ...
});
```

---

## Access from Callbacks

### Getting PersistenceManager

```php
use Noem\State\Feature\Persistence\PersistenceManager;

->onAction('state', function($t) {
    // Access via Runtime (if extended)
    $runtime = RuntimeRegistry::get($this->getRegion());
    $persistence = $runtime->getPersistenceManager();

    // Or via ChainMail
    $persistence = $this->chainMail->get(PersistenceManager::class);

    // Create snapshot
    $snapshot = $persistence->snapshot($runtime);
    file_put_contents('/tmp/checkpoint.snapshot', $snapshot);
})
```

---

## Complete Usage Example

### eCommerce Order Workflow with Persistence

```php
<?php

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Persistence\PersistenceFeature;
use Noem\State\Feature\Persistence\Backend\DatabaseBackend;
use Noem\State\Feature\Persistence\SerializationPolicy;
use Noem\State\Feature\Loader\Holon;
use Noem\State\StandardRuntime;

// Configure persistence
$pdo = new PDO('mysql:host=localhost;dbname=shop', 'user', 'pass');

$policy = (new SerializationPolicy())
    ->exclude('db_connection', 'cache', 'temp')
    ->registerSerializer(
        OrderId::class,
        serialize: fn(OrderId $id) => $id->toString(),
        deserialize: fn(string $s) => OrderId::fromString($s)
    );

$backend = new DatabaseBackend($pdo, 'order_snapshots');

// Load workflow
$order = Order::find($orderId);

$region = Holon::fromYaml('order-workflow.yml')
    ->enableFeatures(
        new ExtendedState(),
        new PersistenceFeature($backend, $policy)
    )
    ->registerFunction('validateOrder', fn($t) => validateOrder($order))
    ->registerFunction('processPayment', fn($t) => processPayment($order))
    ->registerFunction('fulfillOrder', fn($t) => fulfillOrder($order))
    ->bootstrap(['order_id' => $order->id]);

$runtime = new StandardRuntime($region);

// Run until payment required
$runtime->run();

if ($region->getCurrentState() === 'awaiting_payment') {
    // Save state
    $persistence = $region->getPersistenceManager();
    $snapshotId = $persistence->capture($runtime);

    // Store snapshot ID with order
    $order->update(['snapshot_id' => $snapshotId]);

    // Send payment link to customer
    sendPaymentEmail($order);
}

// Later: Payment webhook received
function handlePaymentWebhook(string $orderId, PaymentEvent $event): void
{
    $order = Order::find($orderId);

    // Restore workflow
    $persistence = new PersistenceManager($backend, $policy);
    $runtime = $persistence->restore($order->snapshot_id);

    // Update context
    $region = $runtime->getRegion();
    $region->trigger((object)[
        'type' => 'payment_received',
        'payment_id' => $event->id
    ]);

    // Continue execution
    $runtime->run();

    // Update snapshot if not complete
    if (!$runtime->isComplete()) {
        $snapshotId = $persistence->capture($runtime);
        $order->update(['snapshot_id' => $snapshotId]);
    } else {
        // Clean up
        $order->update(['snapshot_id' => null]);
    }
}
```

---

## Type Annotations

### Full Type Signature

```php
namespace Noem\State\Feature\Persistence;

/**
 * @template T
 */
interface PersistenceBackend
{
    /**
     * @param array{
     *   version: string,
     *   timestamp: int,
     *   runtime: array,
     *   region: array,
     *   context: array,
     *   meta: array
     * } $snapshot
     * @return string
     * @throws SerializationException
     */
    public function serialize(array $snapshot): string;

    /**
     * @param string $data
     * @return array{
     *   version: string,
     *   timestamp: int,
     *   runtime: array,
     *   region: array,
     *   context: array,
     *   meta: array
     * }
     * @throws SerializationException
     */
    public function deserialize(string $data): array;

    /**
     * @param string $data
     * @return bool
     */
    public function validate(string $data): bool;
}

class SerializationPolicy
{
    /**
     * @template T
     * @param class-string<T> $type
     * @param callable(T): array $serializer
     * @param callable(array): T $deserializer
     * @return $this
     */
    public function registerSerializer(
        string $type,
        callable $serializer,
        callable $deserializer
    ): self;
}
```

---

**Last Updated**: 2025-12-28
**API Version**: 1.0.0 (Draft)
**Status**: Subject to change during implementation
