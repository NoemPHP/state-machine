# Persistence Layer - Usage Examples

**Date**: 2025-12-28
**Related**: persistence-layer.md, technical-research.md

---

## Quick Start

### Basic Pause/Resume

```php
use Noem\State\Feature\Persistence\PersistenceFeature;
use Noem\State\Feature\Persistence\Backend\JsonBackend;
use Noem\State\RegionBuilder;
use Noem\State\StandardRuntime;

// 1. Create machine with persistence enabled
$region = (new RegionBuilder())
    ->enableFeatures(
        new ExtendedState(),
        new PersistenceFeature(backend: new JsonBackend())
    )
    ->setStates('idle', 'processing', 'done')
    ->addTransition('idle', 'processing', fn($t) => true)
    ->addTransition('processing', 'done', fn($t) => $this->get('count') >= 10)
    ->onAction('processing', function($t) {
        $count = $this->get('count', 0);
        $this->set('count', $count + 1);
    })
    ->build();

$runtime = new StandardRuntime($region);

// 2. Execute for a while
$runtime->run(steps: 5);

// 3. Pause - capture snapshot
$persistence = $runtime->getPersistenceManager();
$snapshot = $persistence->capture($runtime);

// Save to file
file_put_contents('workflow.snapshot', $snapshot);

// 4. Later - resume from snapshot
$snapshot = file_get_contents('workflow.snapshot');
$restored = $persistence->restore($snapshot);

// Continue execution
$restored->run(); // Picks up from step 5
```

---

## Example 1: Long-Running Order Processing

### Scenario

An eCommerce system needs to process orders through multiple stages:
1. Validate order
2. Check inventory
3. Wait for payment (external)
4. Fulfill order
5. Ship

The payment step requires external user action and may take hours/days.

### Implementation

```php
// order-workflow.yml
states:
  validate:
    on_enter: validateOrder
  check_inventory:
    on_enter: checkInventory
  await_payment:
    on_enter: requestPayment
  fulfill:
    on_enter: fulfillOrder
  ship:
    on_enter: shipOrder
  completed:
    type: final

transitions:
  - from: validate
    to: check_inventory
    guard: orderValid

  - from: check_inventory
    to: await_payment
    guard: inventoryAvailable

  - from: check_inventory
    to: completed
    guard: outOfStock

  - from: await_payment
    to: fulfill
    guard: paymentReceived

  - from: fulfill
    to: ship
    guard: fulfilled

  - from: ship
    to: completed

context:
  order_id: null
  payment_url: null
  fulfillment_id: null

  persistence:
    enabled: true
    backend: database
    exclude:
      - temp_data
```

### PHP Implementation

```php
// OrderProcessor.php

class OrderProcessor
{
    public function __construct(
        private PersistenceManager $persistence,
        private OrderRepository $orders
    ) {}

    public function startOrder(Order $order): void
    {
        // Load workflow definition
        $region = Holon::fromYaml('order-workflow.yml')
            ->registerFunction('validateOrder', fn($t) => $this->validateOrder($order))
            ->registerFunction('checkInventory', fn($t) => $this->checkInventory($order))
            ->registerFunction('requestPayment', fn($t) => $this->requestPayment($order))
            ->registerFunction('fulfillOrder', fn($t) => $this->fulfillOrder($order))
            ->registerFunction('shipOrder', fn($t) => $this->shipOrder($order))
            ->registerFunction('orderValid', fn($t) => $this->get('valid') === true)
            ->registerFunction('inventoryAvailable', fn($t) => $this->get('in_stock') === true)
            ->registerFunction('paymentReceived', fn($t) => $this->get('paid') === true)
            ->registerFunction('fulfilled', fn($t) => $this->get('fulfilled') === true)
            ->bootstrap(['order_id' => $order->id]);

        $runtime = new StandardRuntime($region);

        // Run until we hit await_payment state
        $runtime->run();

        if ($region->getCurrentState() === 'await_payment') {
            // Save state and wait for webhook
            $snapshot = $this->persistence->capture($runtime);
            $this->orders->saveWorkflowState($order->id, $snapshot);

            // Send payment link to customer
            $paymentUrl = $region->getContext('payment_url');
            $this->sendPaymentEmail($order, $paymentUrl);
        }
    }

    public function handlePaymentWebhook(string $orderId, PaymentEvent $event): void
    {
        // Load saved workflow state
        $snapshot = $this->orders->getWorkflowState($orderId);
        $runtime = $this->persistence->restore($snapshot);

        // Update payment status in context
        $region = $runtime->getRegion();
        $region->trigger((object)[
            'type' => 'payment_received',
            'updateContext' => fn() => $this->set('paid', true)
        ]);

        // Resume workflow - will continue to fulfillment
        $runtime->run();

        // Save final state
        $snapshot = $this->persistence->capture($runtime);
        $this->orders->saveWorkflowState($orderId, $snapshot);
    }

    // Helper methods...
    private function validateOrder(Order $order): void
    {
        $valid = $order->total > 0 && $order->items_count > 0;
        $this->set('valid', $valid);
    }

    private function checkInventory(Order $order): void
    {
        $inStock = $this->inventoryService->check($order->items);
        $this->set('in_stock', $inStock);
    }

    private function requestPayment(Order $order): void
    {
        $paymentUrl = $this->paymentGateway->createPaymentLink($order);
        $this->set('payment_url', $paymentUrl);
    }

    private function fulfillOrder(Order $order): void
    {
        $fulfillmentId = $this->fulfillmentService->fulfill($order);
        $this->set('fulfillment_id', $fulfillmentId);
        $this->set('fulfilled', true);
    }

    private function shipOrder(Order $order): void
    {
        $this->shippingService->ship($order);
    }
}
```

---

## Example 2: Crash Recovery with Periodic Snapshots

### Scenario

A data processing pipeline that:
- Processes millions of records
- Takes hours to complete
- Must survive crashes/restarts
- Should resume from last checkpoint

### Implementation

```php
// DataPipeline.php

class DataPipeline
{
    private const SNAPSHOT_INTERVAL = 1000; // Snapshot every 1000 records

    public function __construct(
        private PersistenceManager $persistence,
        private string $snapshotPath = '/var/snapshots'
    ) {}

    public function process(string $dataFile): void
    {
        // Check for existing snapshot
        $snapshotFile = $this->getSnapshotPath($dataFile);

        if (file_exists($snapshotFile)) {
            echo "Resuming from snapshot...\n";
            $runtime = $this->loadSnapshot($snapshotFile);
        } else {
            echo "Starting fresh...\n";
            $runtime = $this->createPipeline($dataFile);
        }

        $region = $runtime->getRegion();
        $lastSnapshot = 0;

        // Process with periodic snapshots
        while (!$runtime->isComplete()) {
            $runtime->run(steps: self::SNAPSHOT_INTERVAL);

            $processed = $region->getContext('processed_count');

            // Create checkpoint snapshot
            if ($processed - $lastSnapshot >= self::SNAPSHOT_INTERVAL) {
                echo "Creating checkpoint at record {$processed}...\n";
                $this->saveSnapshot($runtime, $snapshotFile);
                $lastSnapshot = $processed;
            }
        }

        // Clean up snapshot after successful completion
        if (file_exists($snapshotFile)) {
            unlink($snapshotFile);
        }

        echo "Pipeline completed!\n";
    }

    private function createPipeline(string $dataFile): StandardRuntime
    {
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new PersistenceFeature()
            )
            ->setStates('loading', 'processing', 'done')
            ->onEnter('loading', function($t) use ($dataFile) {
                $this->set('file', $dataFile);
                $this->set('processed_count', 0);
                $this->set('error_count', 0);
                $this->set('records', $this->loadRecords($dataFile));
            })
            ->onAction('processing', function($t) {
                $records = $this->get('records', []);
                $processed = $this->get('processed_count', 0);

                if ($processed >= count($records)) {
                    $this->dispatch((object)['type' => 'all_done']);
                    return;
                }

                try {
                    $record = $records[$processed];
                    $this->processRecord($record);
                    $this->set('processed_count', $processed + 1);
                } catch (\Exception $e) {
                    $errors = $this->get('error_count', 0);
                    $this->set('error_count', $errors + 1);
                    $this->set('processed_count', $processed + 1);
                }
            })
            ->addTransition('loading', 'processing', fn($t) => true)
            ->addTransition('processing', 'done', fn($t) => $t->type === 'all_done')
            ->build();

        return new StandardRuntime($region);
    }

    private function saveSnapshot(StandardRuntime $runtime, string $path): void
    {
        $snapshot = $this->persistence->capture($runtime);
        file_put_contents($path, $snapshot);
    }

    private function loadSnapshot(string $path): StandardRuntime
    {
        $snapshot = file_get_contents($path);
        return $this->persistence->restore($snapshot);
    }

    private function getSnapshotPath(string $dataFile): string
    {
        $hash = md5($dataFile);
        return "{$this->snapshotPath}/pipeline-{$hash}.snapshot";
    }

    // ... other methods
}

// Usage
$pipeline = new DataPipeline($persistence);

try {
    $pipeline->process('data/large-dataset.csv');
} catch (\Exception $e) {
    echo "Pipeline crashed: {$e->getMessage()}\n";
    echo "Snapshot saved - run again to resume\n";
}
```

---

## Example 3: Testing with Snapshots

### Scenario

Testing complex state machine behavior by:
- Creating snapshots at key decision points
- Running multiple test scenarios from same snapshot
- Avoiding expensive setup in each test

### Implementation

```php
// WorkflowTest.php

class WorkflowTest extends TestCase
{
    private PersistenceManager $persistence;
    private string $snapshotDir;

    protected function setUp(): void
    {
        $this->persistence = new PersistenceManager(new JsonBackend());
        $this->snapshotDir = sys_get_temp_dir() . '/workflow-snapshots';

        if (!is_dir($this->snapshotDir)) {
            mkdir($this->snapshotDir);
        }
    }

    public function testApprovalWorkflow_ApprovedPath(): void
    {
        // Start from "awaiting approval" snapshot
        $runtime = $this->loadSnapshot('awaiting_approval');
        $region = $runtime->getRegion();

        // Trigger approval
        $region->trigger((object)[
            'type' => 'approve',
            'approver' => 'manager@example.com'
        ]);

        $runtime->run();

        // Verify approved path
        $this->assertEquals('approved', $region->getCurrentState());
        $this->assertEquals('manager@example.com', $region->getContext('approved_by'));
    }

    public function testApprovalWorkflow_RejectedPath(): void
    {
        // Same starting point
        $runtime = $this->loadSnapshot('awaiting_approval');
        $region = $runtime->getRegion();

        // Trigger rejection
        $region->trigger((object)[
            'type' => 'reject',
            'reason' => 'Budget exceeded'
        ]);

        $runtime->run();

        // Verify rejected path
        $this->assertEquals('rejected', $region->getCurrentState());
        $this->assertEquals('Budget exceeded', $region->getContext('rejection_reason'));
    }

    public function testApprovalWorkflow_EscalationPath(): void
    {
        // Same starting point
        $runtime = $this->loadSnapshot('awaiting_approval');
        $region = $runtime->getRegion();

        // Trigger escalation
        $region->trigger((object)[
            'type' => 'escalate',
            'escalation_level' => 2
        ]);

        $runtime->run();

        // Verify escalation
        $this->assertEquals('awaiting_senior_approval', $region->getCurrentState());
        $this->assertEquals(2, $region->getContext('escalation_level'));
    }

    // Helper to create reusable snapshots
    public static function setUpBeforeClass(): void
    {
        $snapshotDir = sys_get_temp_dir() . '/workflow-snapshots';

        // Create "awaiting approval" snapshot once
        if (!file_exists("{$snapshotDir}/awaiting_approval.snapshot")) {
            $workflow = self::createApprovalWorkflow();
            $runtime = new StandardRuntime($workflow);

            // Run to "awaiting approval" state
            $runtime->run();

            // Save snapshot
            $persistence = new PersistenceManager(new JsonBackend());
            $snapshot = $persistence->capture($runtime);
            file_put_contents("{$snapshotDir}/awaiting_approval.snapshot", $snapshot);
        }
    }

    private function loadSnapshot(string $name): StandardRuntime
    {
        $path = "{$this->snapshotDir}/{$name}.snapshot";
        $snapshot = file_get_contents($path);
        return $this->persistence->restore($snapshot);
    }

    private static function createApprovalWorkflow(): Region
    {
        return (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new PersistenceFeature()
            )
            ->setStates(
                'draft',
                'awaiting_approval',
                'awaiting_senior_approval',
                'approved',
                'rejected'
            )
            ->onEnter('draft', function($t) {
                $this->set('created_at', time());
                $this->set('status', 'draft');
            })
            ->onEnter('awaiting_approval', function($t) {
                $this->set('status', 'pending');
                $this->set('approval_requested_at', time());
            })
            ->addTransition('draft', 'awaiting_approval', fn($t) => $t->type === 'submit')
            ->addTransition('awaiting_approval', 'approved', fn($t) => $t->type === 'approve')
            ->addTransition('awaiting_approval', 'rejected', fn($t) => $t->type === 'reject')
            ->addTransition('awaiting_approval', 'awaiting_senior_approval', fn($t) => $t->type === 'escalate')
            ->build();
    }
}
```

---

## Example 4: Distributed Job Queue

### Scenario

Offload expensive workflows to background workers:
- Main app creates workflow, runs a few steps
- Serializes and pushes to queue
- Worker picks up, resumes execution
- Can scale workers independently

### Implementation

```php
// JobEnqueuer.php

class WorkflowJobEnqueuer
{
    public function __construct(
        private PersistenceManager $persistence,
        private QueueInterface $queue
    ) {}

    public function enqueueWorkflow(Region $region, int $steps = 100): string
    {
        $runtime = new StandardRuntime($region);

        // Execute initial steps locally (fast path)
        $runtime->run(steps: min($steps, 10));

        if ($runtime->isComplete()) {
            return 'completed_immediately';
        }

        // Serialize and enqueue
        $snapshot = $this->persistence->capture($runtime);
        $jobId = $this->queue->push('process_workflow', [
            'snapshot' => $snapshot,
            'remaining_steps' => $steps - 10
        ]);

        return $jobId;
    }
}

// Worker.php

class WorkflowWorker
{
    public function __construct(
        private PersistenceManager $persistence,
        private QueueInterface $queue
    ) {}

    public function processJob(array $job): void
    {
        // Restore workflow from snapshot
        $runtime = $this->persistence->restore($job['snapshot']);

        // Continue execution
        $runtime->run(steps: $job['remaining_steps']);

        if (!$runtime->isComplete()) {
            // Still more work - re-enqueue
            $snapshot = $this->persistence->capture($runtime);
            $this->queue->push('process_workflow', [
                'snapshot' => $snapshot,
                'remaining_steps' => $job['remaining_steps']
            ]);
        } else {
            // Workflow complete - notify
            $this->notifyCompletion($runtime);
        }
    }

    public function run(): void
    {
        while ($job = $this->queue->pop('process_workflow')) {
            try {
                $this->processJob($job);
            } catch (\Exception $e) {
                // Save crash snapshot
                $snapshot = $job['snapshot'];
                file_put_contents(
                    "/var/crashes/crash-{$job['id']}.snapshot",
                    $snapshot
                );

                // Re-enqueue with backoff
                $this->queue->push('process_workflow', $job, delay: 60);
            }
        }
    }
}

// Usage

// Main application
$enqueuer = new WorkflowJobEnqueuer($persistence, $redis);

$region = Holon::fromYaml('expensive-workflow.yml')->bootstrap();
$jobId = $enqueuer->enqueueWorkflow($region, steps: 10000);

echo "Workflow enqueued as job {$jobId}\n";

// Worker process (separate container/process)
$worker = new WorkflowWorker($persistence, $redis);
$worker->run(); // Processes jobs in background
```

---

## Example 5: Debugging with Time-Travel

### Scenario

Capture snapshots periodically during development to:
- Rewind to any point in execution
- Compare state at different times
- Reproduce bugs from crash dumps

### Implementation

```php
// TimeravelDebugger.php

class TimeravelDebugger
{
    private array $timeline = [];

    public function __construct(
        private PersistenceManager $persistence
    ) {}

    public function record(StandardRuntime $runtime, string $label = null): void
    {
        $snapshot = $this->persistence->capture($runtime);

        $this->timeline[] = [
            'timestamp' => microtime(true),
            'label' => $label ?? "Step " . count($this->timeline),
            'snapshot' => $snapshot,
            'state' => $runtime->getRegion()->getCurrentState(),
            'iteration' => $runtime->getIteration(),
        ];
    }

    public function rewindTo(int $index): StandardRuntime
    {
        if (!isset($this->timeline[$index])) {
            throw new \InvalidArgumentException("No snapshot at index {$index}");
        }

        return $this->persistence->restore($this->timeline[$index]['snapshot']);
    }

    public function rewindToLabel(string $label): StandardRuntime
    {
        foreach ($this->timeline as $entry) {
            if ($entry['label'] === $label) {
                return $this->persistence->restore($entry['snapshot']);
            }
        }

        throw new \InvalidArgumentException("No snapshot with label '{$label}'");
    }

    public function showTimeline(): void
    {
        foreach ($this->timeline as $i => $entry) {
            printf(
                "[%d] %s - State: %s, Iteration: %d (%.3fs)\n",
                $i,
                $entry['label'],
                $entry['state'],
                $entry['iteration'],
                $entry['timestamp'] - $this->timeline[0]['timestamp']
            );
        }
    }

    public function exportTimeline(string $path): void
    {
        file_put_contents($path, json_encode($this->timeline, JSON_PRETTY_PRINT));
    }

    public function importTimeline(string $path): void
    {
        $this->timeline = json_decode(file_get_contents($path), true);
    }
}

// Usage

$debugger = new TimeravelDebugger($persistence);
$runtime = createComplexWorkflow();

// Record at key points
$runtime->run(steps: 10);
$debugger->record($runtime, 'after_initialization');

$runtime->run(steps: 50);
$debugger->record($runtime, 'mid_processing');

$runtime->run(steps: 100);
$debugger->record($runtime, 'near_completion');

// Show timeline
$debugger->showTimeline();
// Output:
// [0] after_initialization - State: processing, Iteration: 10 (0.023s)
// [1] mid_processing - State: processing, Iteration: 60 (0.156s)
// [2] near_completion - State: finalizing, Iteration: 160 (0.387s)

// Rewind to investigate
$rewound = $debugger->rewindTo(1);
$region = $rewound->getRegion();

echo "State at mid-processing: {$region->getCurrentState()}\n";
echo "Context: " . json_encode($region->getContext(), JSON_PRETTY_PRINT) . "\n";

// Continue from that point with modifications
$region->trigger(new DebugEvent());
$rewound->run();

// Export for later analysis
$debugger->exportTimeline('debug-session.json');
```

---

## Example 6: Multi-Tenant Workflow Management

### Scenario

SaaS application managing workflows for multiple tenants:
- Each tenant has many workflow instances
- Workflows stored in database
- Load on-demand, execute, save back

### Implementation

```php
// WorkflowRepository.php

class WorkflowRepository
{
    public function __construct(
        private PDO $db,
        private PersistenceManager $persistence
    ) {}

    public function save(string $tenantId, string $workflowId, StandardRuntime $runtime): void
    {
        $snapshot = $this->persistence->capture($runtime);

        $stmt = $this->db->prepare(
            "INSERT INTO workflows (tenant_id, workflow_id, snapshot, updated_at)
             VALUES (:tenant, :workflow, :snapshot, :updated)
             ON DUPLICATE KEY UPDATE snapshot = :snapshot, updated_at = :updated"
        );

        $stmt->execute([
            'tenant' => $tenantId,
            'workflow' => $workflowId,
            'snapshot' => $snapshot,
            'updated' => time()
        ]);
    }

    public function load(string $tenantId, string $workflowId): ?StandardRuntime
    {
        $stmt = $this->db->prepare(
            "SELECT snapshot FROM workflows
             WHERE tenant_id = :tenant AND workflow_id = :workflow"
        );

        $stmt->execute([
            'tenant' => $tenantId,
            'workflow' => $workflowId
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->persistence->restore($row['snapshot']);
    }

    public function findByState(string $tenantId, string $state): array
    {
        // Query workflows in specific state
        $stmt = $this->db->prepare(
            "SELECT workflow_id, snapshot FROM workflows
             WHERE tenant_id = :tenant
             AND JSON_EXTRACT(snapshot, '$.region.currentState') = :state"
        );

        $stmt->execute([
            'tenant' => $tenantId,
            'state' => $state
        ]);

        $results = [];
        while ($row = $stmt->fetch()) {
            $results[$row['workflow_id']] = $this->persistence->restore($row['snapshot']);
        }

        return $results;
    }

    public function resume(string $tenantId, string $workflowId, object $event): void
    {
        $runtime = $this->load($tenantId, $workflowId);

        if (!$runtime) {
            throw new \RuntimeException("Workflow not found: {$workflowId}");
        }

        $region = $runtime->getRegion();
        $region->trigger($event);
        $runtime->run();

        $this->save($tenantId, $workflowId, $runtime);
    }
}

// WorkflowController.php

class WorkflowController
{
    public function __construct(
        private WorkflowRepository $repository
    ) {}

    public function createWorkflow(string $tenantId, string $type, array $data): string
    {
        $workflowId = Uuid::v4();

        // Create workflow from template
        $region = $this->loadTemplate($type, $data);
        $runtime = new StandardRuntime($region);

        // Execute initial steps
        $runtime->run(steps: 10);

        // Save to database
        $this->repository->save($tenantId, $workflowId, $runtime);

        return $workflowId;
    }

    public function resumeWorkflow(string $tenantId, string $workflowId, object $event): void
    {
        $this->repository->resume($tenantId, $workflowId, $event);
    }

    public function getPendingApprovals(string $tenantId): array
    {
        return $this->repository->findByState($tenantId, 'awaiting_approval');
    }

    private function loadTemplate(string $type, array $data): Region
    {
        return Holon::fromYaml("templates/{$type}.yml")
            ->enableFeatures(
                new ExtendedState(),
                new PersistenceFeature(backend: new DatabaseBackend($this->db))
            )
            ->bootstrap($data);
    }
}
```

---

## Best Practices

### 1. Exclude Non-Serializable Data

```php
// ❌ Bad - stores database connection
->onEnter('state', function($t) {
    $this->set('db', new PDO(...));
});

// ✅ Good - exclude from serialization
$policy = (new SerializationPolicy())
    ->exclude('db', 'redis', 'cache');

// ✅ Better - don't store at all, inject when needed
->onEnter('state', function($t) use ($db) {
    // Use closure capture instead of context
    $results = $db->query('...');
    $this->set('results', $results); // Only store serializable results
});
```

### 2. Idempotent Async Operations

```php
// ❌ Bad - not resumable
->onEnter('state', function($t) {
    yield fetch('api.example.com')->then(
        fn($data) => $this->set('data', $data)
    );
});

// ✅ Good - resumable
->onEnter('state', function($t) {
    if (!$this->get('fetched')) {
        yield fetch('api.example.com')->then(function($data) {
            $this->set('data', $data);
            $this->set('fetched', true);
        });
    }
});
```

### 3. Version Snapshots

```php
// Add version/hash to snapshot for validation
$snapshot = $persistence->capture($runtime);
$versioned = json_encode([
    'version' => '1.0.0',
    'machine_hash' => hash('sha256', file_get_contents('workflow.yml')),
    'created_at' => time(),
    'snapshot' => $snapshot
]);

// Validate on restore
$data = json_decode($stored, true);
if ($data['machine_hash'] !== $currentHash) {
    throw new IncompatibleSnapshotException(
        'Workflow definition has changed since snapshot was created'
    );
}
```

### 4. Cleanup Completed Workflows

```php
// Periodically clean up completed workflow snapshots
$db->execute(
    "DELETE FROM workflows
     WHERE JSON_EXTRACT(snapshot, '$.region.currentState') = :final
     AND updated_at < :cutoff",
    [
        'final' => 'completed',
        'cutoff' => time() - (7 * 86400) // 7 days ago
    ]
);
```

### 5. Monitor Snapshot Size

```php
$snapshot = $persistence->capture($runtime);
$size = strlen($snapshot);

if ($size > 1024 * 1024) { // > 1MB
    trigger_error(
        "Workflow snapshot is {$size} bytes. Consider excluding large context data.",
        E_USER_WARNING
    );
}
```

---

## Common Pitfalls

### 1. Storing Closures in Context

```php
// ❌ Will fail to serialize
$this->set('callback', fn() => doSomething());

// ✅ Use named function reference
$this->set('callback_name', 'doSomething');

// Later:
$callbackName = $this->get('callback_name');
$callbackName();
```

### 2. Large Arrays in Context

```php
// ❌ Stores entire dataset in context
$this->set('all_records', $millionRecords);

// ✅ Store reference/cursor
$this->set('record_offset', 1234);
$this->set('batch_size', 100);

// Fetch on demand
$offset = $this->get('record_offset');
$batch = fetchRecords($offset, 100);
```

### 3. Assuming Immediate Completion

```php
// ❌ Assumes workflow completes instantly
$runtime->run();
echo "Done!";

// ✅ Check completion, handle snapshots
if (!$runtime->run()) {
    // Not complete - save and schedule continuation
    $snapshot = $persistence->capture($runtime);
    scheduleResume($snapshot, delay: 60);
} else {
    echo "Done!";
}
```

---

**Last Updated**: 2025-12-28
