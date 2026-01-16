# Persistence Layer - Performance Analysis

**Status**: Draft
**Created**: 2025-12-28
**Related**: [README.md](./README.md)

## Overview

This document analyzes the expected performance characteristics of the Persistence Layer, including:
- Snapshot size estimates
- Serialization/deserialization speed
- Memory usage
- Storage requirements
- Optimization strategies

---

## Snapshot Size Estimation

### Baseline: Empty Region

```php
// Minimal Region (no context, single state)
$region = (new RegionBuilder())
    ->setStates('idle', 'done')
    ->build();

$runtime = new StandardRuntime($region);
$snapshot = $persistence->capture($runtime);
```

**Expected JSON size**: ~500 bytes

**Breakdown**:
```json
{
  "version": "1.0.0",                    // ~20 bytes
  "timestamp": 1703779200,               // ~15 bytes
  "runtime": {
    "type": "StandardRuntime",           // ~30 bytes
    "iteration": 0,                      // ~15 bytes
    "complete": false,                   // ~20 bytes
    "config": {                          // ~100 bytes
      "maxIterations": 0
    }
  },
  "region": {
    "currentState": "idle",              // ~30 bytes
    "dispatched": [],                    // ~20 bytes
    "initialStateEntered": false,        // ~35 bytes
    "final": "done",                     // ~20 bytes
    "states": ["idle", "done"]           // ~40 bytes
  },
  "context": {},                         // ~15 bytes
  "meta": {}                             // ~15 bytes
}
```

**Total**: ~375 bytes uncompressed JSON (~500 bytes with formatting)

---

### Small Workflow (eCommerce Order)

```php
// Order processing workflow
$region = Holon::fromYaml('order-workflow.yml')
    ->bootstrap([
        'order_id' => '12345',
        'items' => [
            ['sku' => 'PROD-001', 'qty' => 2, 'price' => 29.99],
            ['sku' => 'PROD-002', 'qty' => 1, 'price' => 49.99]
        ],
        'subtotal' => 109.97,
        'shipping_address' => [
            'street' => '123 Main St',
            'city' => 'Portland',
            'state' => 'OR',
            'zip' => '97201'
        ],
        'payment_method' => 'card',
        'status' => 'pending'
    ]);
```

**Expected JSON size**: ~2-3 KB

**Breakdown**:
- Base structure: ~500 bytes
- Context data: ~1,500 bytes (order details)
- State definitions: ~500 bytes (5-10 states)
- Meta data: ~200 bytes

**Total**: ~2.7 KB uncompressed

**With gzip compression**: ~800 bytes (70% reduction)

---

### Medium Workflow (Data Processing Pipeline)

```php
// Processing 10,000 records
$context = [
    'processed_count' => 5432,
    'error_count' => 23,
    'errors' => [ /* last 100 errors */ ],
    'statistics' => [
        'avg_duration' => 0.023,
        'max_duration' => 1.234,
        'min_duration' => 0.001
    ],
    'checkpoint_data' => [ /* processing state */ ]
];
```

**Expected JSON size**: ~50-100 KB

**Breakdown**:
- Base structure: ~500 bytes
- Context data: ~80 KB (processing state, errors)
- Dispatched queue: ~5 KB (pending events)
- Meta data: ~10 KB (feature data)

**Total**: ~95 KB uncompressed

**With gzip compression**: ~15 KB (84% reduction)

---

### Large Workflow (Complex Multi-Region)

```php
// Parent with 10 child regions
$parent = (new RegionBuilder())
    ->enableFeatures(new ExtendedState())
    ->setStates('orchestrating', 'done')
    ->build();

// 10 children, each with context
foreach (range(1, 10) as $i) {
    $child = (new RegionBuilder())
        ->enableFeatures(new ExtendedState())
        ->setStates('working', 'finished')
        ->build(['data' => range(1, 1000)]); // 1000 items each

    $parent->connect($child, Connection::RECEIVE_META);
}
```

**Expected JSON size**: ~500 KB - 1 MB

**Breakdown**:
- Parent: ~3 KB
- 10 children × ~50 KB each: ~500 KB
- Connection metadata: ~10 KB
- Shared context: ~50 KB

**Total**: ~563 KB uncompressed

**With gzip compression**: ~100 KB (82% reduction)

---

## Size Comparison by Backend

| Backend | Empty Region | Small Workflow | Medium Workflow | Large Workflow |
|---------|--------------|----------------|-----------------|----------------|
| **JSON (pretty)** | 500 B | 2.7 KB | 95 KB | 563 KB |
| **JSON (compact)** | 375 B | 2.1 KB | 78 KB | 481 KB |
| **JSON + gzip** | 150 B | 800 B | 15 KB | 100 KB |
| **igbinary** | 280 B | 1.5 KB | 52 KB | 320 KB |
| **MessagePack** | 250 B | 1.3 KB | 48 KB | 290 KB |
| **MessagePack + gzip** | 120 B | 600 B | 12 KB | 85 KB |

**Key Takeaways**:
- JSON + gzip: Best for readability + size
- MessagePack + gzip: Best absolute compression
- igbinary: Good middle ground, widely available

---

## Serialization Speed

### Benchmark Setup

```php
class PerformanceBenchmark
{
    public function benchmarkSerialize(Region $region, int $iterations = 1000): array
    {
        $persistence = new PersistenceManager(new JsonBackend());
        $runtime = new StandardRuntime($region);

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $snapshot = $persistence->capture($runtime);
            $times[] = (hrtime(true) - $start) / 1e9; // Convert to seconds
        }

        return [
            'min' => min($times),
            'max' => max($times),
            'avg' => array_sum($times) / count($times),
            'median' => $this->median($times),
            'p95' => $this->percentile($times, 95),
            'p99' => $this->percentile($times, 99)
        ];
    }
}
```

### Expected Performance (JSON Backend)

| Workflow Size | Avg Time | P95 Time | P99 Time | Throughput |
|---------------|----------|----------|----------|------------|
| **Empty** | 0.2 ms | 0.3 ms | 0.5 ms | 5,000/sec |
| **Small (3 KB)** | 0.5 ms | 0.8 ms | 1.2 ms | 2,000/sec |
| **Medium (100 KB)** | 3.0 ms | 4.5 ms | 6.0 ms | 333/sec |
| **Large (500 KB)** | 15 ms | 22 ms | 30 ms | 67/sec |

### Expected Performance (igbinary Backend)

| Workflow Size | Avg Time | P95 Time | P99 Time | Throughput |
|---------------|----------|----------|----------|------------|
| **Empty** | 0.1 ms | 0.15 ms | 0.25 ms | 10,000/sec |
| **Small (3 KB)** | 0.3 ms | 0.5 ms | 0.8 ms | 3,333/sec |
| **Medium (100 KB)** | 1.8 ms | 2.7 ms | 3.6 ms | 556/sec |
| **Large (500 KB)** | 9 ms | 13 ms | 18 ms | 111/sec |

**Key Takeaways**:
- igbinary: ~2x faster than JSON
- Small snapshots: Sub-millisecond performance
- Large snapshots: Still sub-20ms for 500KB

---

## Deserialization Speed

### Expected Performance (JSON Backend)

| Workflow Size | Avg Time | P95 Time | P99 Time | Notes |
|---------------|----------|----------|----------|-------|
| **Empty** | 0.3 ms | 0.5 ms | 0.8 ms | + Region rebuild |
| **Small (3 KB)** | 0.8 ms | 1.2 ms | 1.8 ms | + Context restore |
| **Medium (100 KB)** | 5.0 ms | 7.5 ms | 10 ms | + Validation |
| **Large (500 KB)** | 25 ms | 35 ms | 45 ms | + Connection setup |

**Deserialization overhead**: ~1.5x slower than serialization due to:
- JSON parsing
- Object reconstruction
- Context restoration
- Region rebuilding
- Connection setup

---

## Memory Usage

### Serialization Memory

```php
// Peak memory during capture()
class MemoryProfiler
{
    public function profileCapture(Runtime $runtime): array
    {
        $baseline = memory_get_usage();
        $snapshot = $persistence->capture($runtime);
        $peak = memory_get_peak_usage();

        return [
            'baseline' => $baseline,
            'peak' => $peak,
            'increase' => $peak - $baseline,
            'snapshot_size' => strlen($snapshot),
            'overhead_ratio' => ($peak - $baseline) / strlen($snapshot)
        ];
    }
}
```

### Expected Memory Usage

| Workflow Size | Snapshot Size | Peak Memory | Overhead Ratio |
|---------------|---------------|-------------|----------------|
| **Empty** | 500 B | 2 KB | 4x |
| **Small** | 3 KB | 15 KB | 5x |
| **Medium** | 100 KB | 600 KB | 6x |
| **Large** | 500 KB | 3.5 MB | 7x |

**Overhead sources**:
- Reflection metadata: ~2x
- Temporary data structures: ~2x
- JSON encoding buffer: ~1.5x
- Region traversal: ~1.5x

**Total**: ~5-7x the final snapshot size

---

## Storage Requirements

### Database Storage (MySQL)

```sql
CREATE TABLE workflow_snapshots (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    workflow_type VARCHAR(100) NOT NULL,
    snapshot MEDIUMBLOB NOT NULL,          -- 16 MB max
    snapshot_size INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    expires_at INT UNSIGNED,

    INDEX idx_tenant (tenant_id),
    INDEX idx_type (workflow_type),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Storage estimates** (1 million workflows):

| Avg Snapshot Size | Total Storage | With Indexes | Recommendation |
|-------------------|---------------|--------------|----------------|
| **3 KB** | 3 GB | 4.5 GB | Standard SSD |
| **100 KB** | 100 GB | 150 GB | High-perf SSD |
| **500 KB** | 500 GB | 750 GB | Dedicated storage |

**With 30-day retention**:
- 3 KB × 1M workflows × 30 snapshots/workflow: ~90 GB
- 100 KB × 1M workflows × 5 snapshots/workflow: ~500 GB

---

### Filesystem Storage

**Directory structure**:
```
/var/snapshots/
├── 2025/
│   ├── 12/
│   │   ├── 28/
│   │   │   ├── {uuid-1}.snapshot
│   │   │   ├── {uuid-2}.snapshot
│   │   │   └── ...
```

**Expected I/O performance**:

| Operation | IOPS | Throughput | Notes |
|-----------|------|------------|-------|
| **Write (3 KB)** | 5,000/sec | 15 MB/sec | SSD |
| **Write (100 KB)** | 333/sec | 33 MB/sec | SSD |
| **Read (3 KB)** | 10,000/sec | 30 MB/sec | SSD |
| **Read (100 KB)** | 500/sec | 50 MB/sec | SSD |

**Scaling considerations**:
- 1M snapshots/day × 3 KB: ~3 GB/day (~90 GB/month)
- Use partitioning by date for cleanup
- Consider object storage (S3) for long-term retention

---

## Optimization Strategies

### 1. Lazy Serialization

**Defer expensive operations**:

```php
class LazySerializationPolicy extends SerializationPolicy
{
    public function shouldSerializeImmediately(string $key): bool
    {
        // Skip large arrays during capture
        return !in_array($key, ['large_dataset', 'cache', 'temp']);
    }
}

class LazyPersistenceManager extends PersistenceManager
{
    public function capture(Runtime $runtime): string
    {
        // Capture only essential state immediately
        $snapshot = [
            'version' => '1.0.0',
            'runtime' => $this->serializeRuntime($runtime),
            'region' => $this->serializeRegionState($runtime->getRegion()),
            // Skip context for now
        ];

        return $this->backend->serialize($snapshot);
    }

    public function captureWithContext(Runtime $runtime): string
    {
        // Full capture including context (slower)
        return parent::capture($runtime);
    }
}
```

**Performance gain**: ~50% faster for snapshots without context

---

### 2. Incremental Snapshots

**Only serialize changes**:

```php
class IncrementalBackend implements PersistenceBackend
{
    private ?array $baseline = null;

    public function serialize(array $snapshot): string
    {
        if ($this->baseline === null) {
            // First snapshot: full capture
            $this->baseline = $snapshot;
            return json_encode(['type' => 'full', 'data' => $snapshot]);
        }

        // Subsequent: compute diff
        $diff = $this->computeDiff($this->baseline, $snapshot);
        $this->baseline = $snapshot;

        return json_encode(['type' => 'delta', 'data' => $diff]);
    }

    private function computeDiff(array $old, array $new): array
    {
        $diff = [];

        foreach ($new as $key => $value) {
            if (!isset($old[$key]) || $old[$key] !== $value) {
                $diff[$key] = $value;
            }
        }

        return $diff;
    }
}
```

**Size reduction**: 80-95% for periodic snapshots
**Performance gain**: 3-5x faster serialization

---

### 3. Compression

**Enable gzip compression**:

```php
class CompressedBackend implements PersistenceBackend
{
    public function __construct(
        private PersistenceBackend $inner,
        private int $level = 6 // Compression level 1-9
    ) {}

    public function serialize(array $snapshot): string
    {
        $json = $this->inner->serialize($snapshot);
        return gzencode($json, $this->level);
    }

    public function deserialize(string $data): array
    {
        $json = gzdecode($data);
        return $this->inner->deserialize($json);
    }
}
```

**Trade-offs**:

| Level | Size Reduction | CPU Time | Recommendation |
|-------|----------------|----------|----------------|
| **1** | 60% | +0.5 ms | High throughput |
| **6** | 75% | +2 ms | Balanced (default) |
| **9** | 80% | +8 ms | Storage-constrained |

---

### 4. Parallel Serialization

**Serialize region tree in parallel**:

```php
class ParallelPersistenceManager extends PersistenceManager
{
    public function capture(Runtime $runtime): string
    {
        $region = $runtime->getRegion();
        $children = $this->getConnectedChildren($region);

        // Serialize children in parallel
        $childSnapshots = [];
        foreach ($children as $child) {
            $childSnapshots[] = new Promise(function() use ($child) {
                return $this->serializeRegion($child);
            });
        }

        // Wait for all children
        $childData = Promise::all($childSnapshots);

        // Combine
        $snapshot = [
            'region' => $this->serializeRegion($region),
            'children' => $childData
        ];

        return $this->backend->serialize($snapshot);
    }
}
```

**Performance gain**: 2-3x for large hierarchical machines

---

### 5. Caching

**Cache serialized chunks**:

```php
class CachedPersistenceManager extends PersistenceManager
{
    private array $cache = [];

    public function capture(Runtime $runtime): string
    {
        $region = $runtime->getRegion();
        $regionId = spl_object_id($region);

        // Check cache
        if (isset($this->cache[$regionId])) {
            $cached = $this->cache[$regionId];

            // Check if region state changed
            if ($cached['state'] === $region->getCurrentState()
                && $cached['iteration'] === $runtime->getIteration()
            ) {
                // Reuse cached snapshot
                return $cached['snapshot'];
            }
        }

        // Serialize
        $snapshot = parent::capture($runtime);

        // Cache
        $this->cache[$regionId] = [
            'state' => $region->getCurrentState(),
            'iteration' => $runtime->getIteration(),
            'snapshot' => $snapshot
        ];

        return $snapshot;
    }
}
```

**Performance gain**: 10-100x for repeated captures of unchanged state

---

## Performance Targets

### Phase 1 (MVP) Targets

| Metric | Target | Stretch Goal |
|--------|--------|--------------|
| **Serialize (small)** | < 1 ms | < 0.5 ms |
| **Serialize (medium)** | < 10 ms | < 5 ms |
| **Deserialize (small)** | < 2 ms | < 1 ms |
| **Deserialize (medium)** | < 20 ms | < 10 ms |
| **Memory overhead** | < 10x | < 5x |
| **Snapshot size (small)** | < 5 KB | < 3 KB |

### Phase 3 (Production) Targets

| Metric | Target | Stretch Goal |
|--------|--------|--------------|
| **Serialize (large)** | < 50 ms | < 25 ms |
| **Deserialize (large)** | < 100 ms | < 50 ms |
| **Throughput** | > 100/sec | > 500/sec |
| **Compression ratio** | > 70% | > 80% |

---

## Performance Testing

### Benchmark Suite

```php
class PersistenceBenchmarks extends TestCase
{
    public function benchmarkSmallWorkflow(): void
    {
        $region = $this->createSmallWorkflow();
        $runtime = new StandardRuntime($region);
        $persistence = new PersistenceManager(new JsonBackend());

        // Warmup
        for ($i = 0; $i < 100; $i++) {
            $persistence->capture($runtime);
        }

        // Benchmark
        $start = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $snapshot = $persistence->capture($runtime);
        }
        $elapsed = (hrtime(true) - $start) / 1e9;

        $avgTime = $elapsed / 1000;

        $this->assertLessThan(0.001, $avgTime, 'Serialize small < 1ms');
    }

    public function benchmarkMemoryUsage(): void
    {
        $region = $this->createMediumWorkflow();
        $runtime = new StandardRuntime($region);
        $persistence = new PersistenceManager(new JsonBackend());

        $baseline = memory_get_usage();
        $snapshot = $persistence->capture($runtime);
        $peak = memory_get_peak_usage();

        $overhead = $peak - $baseline;
        $ratio = $overhead / strlen($snapshot);

        $this->assertLessThan(10, $ratio, 'Memory overhead < 10x');
    }

    public function benchmarkCompressionRatio(): void
    {
        $region = $this->createLargeWorkflow();
        $runtime = new StandardRuntime($region);

        $json = new PersistenceManager(new JsonBackend());
        $compressed = new PersistenceManager(
            new CompressedBackend(new JsonBackend())
        );

        $jsonSnapshot = $json->capture($runtime);
        $compressedSnapshot = $compressed->capture($runtime);

        $ratio = strlen($compressedSnapshot) / strlen($jsonSnapshot);

        $this->assertLessThan(0.3, $ratio, 'Compression > 70%');
    }
}
```

---

## Monitoring Metrics

### Production Metrics

```php
class PersistenceMetrics
{
    public function recordCapture(
        string $workflowType,
        int $snapshotSize,
        float $duration,
        int $memoryUsed
    ): void {
        // Prometheus-style metrics
        $this->histogram('persistence_serialize_duration_seconds', $duration, [
            'workflow_type' => $workflowType
        ]);

        $this->histogram('persistence_snapshot_size_bytes', $snapshotSize, [
            'workflow_type' => $workflowType
        ]);

        $this->histogram('persistence_memory_usage_bytes', $memoryUsed, [
            'workflow_type' => $workflowType
        ]);

        $this->counter('persistence_captures_total', 1, [
            'workflow_type' => $workflowType
        ]);
    }
}
```

### Grafana Dashboard Queries

```promql
# P95 serialization latency
histogram_quantile(0.95, persistence_serialize_duration_seconds)

# Average snapshot size by workflow type
avg(persistence_snapshot_size_bytes) by (workflow_type)

# Capture rate
rate(persistence_captures_total[5m])

# Memory overhead ratio
persistence_memory_usage_bytes / persistence_snapshot_size_bytes
```

---

## Summary

### Expected Performance Profile

| Workflow Size | Serialize | Deserialize | Size (JSON) | Size (Compressed) |
|---------------|-----------|-------------|-------------|-------------------|
| **Empty** | 0.2 ms | 0.3 ms | 500 B | 150 B |
| **Small** | 0.5 ms | 0.8 ms | 3 KB | 800 B |
| **Medium** | 3 ms | 5 ms | 100 KB | 15 KB |
| **Large** | 15 ms | 25 ms | 500 KB | 100 KB |

### Optimization Priority

1. **Phase 1 (MVP)**: Focus on correctness, accept baseline performance
2. **Phase 2**: Add compression (easy 70% size reduction)
3. **Phase 3**: Implement igbinary backend (2x speed improvement)
4. **Phase 4**: Add incremental snapshots (5x speed + 90% size reduction)
5. **Future**: Parallel serialization for hierarchical machines

---

**Last Updated**: 2025-12-28
**Next Review**: After Phase 1 implementation (measure actual vs. estimates)
