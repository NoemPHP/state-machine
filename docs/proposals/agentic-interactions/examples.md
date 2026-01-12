# Agentic Interaction Patterns - Usage Examples

**Part of**: [Agentic Interactions Proposal](./README.md)
**Focus**: Practical real-world usage scenarios and code examples

---

## Table of Contents

1. [Basic Patterns](#basic-patterns)
2. [Machine-Agent Scenarios](#machine-agent-scenarios)
3. [Multi-Agent Coordination](#multi-agent-coordination)
4. [CLI Workflows](#cli-workflows)
5. [Web Application Integration](#web-application-integration)
6. [Advanced Patterns](#advanced-patterns)

---

## Basic Patterns

### 1. Simple Confirmation

**Scenario**: Confirm before destructive action

```php
// In state machine
->onEnter('ready_to_delete', function(object $t): \Generator {
    $itemName = $this->get('item_name');

    $confirmed = yield from $this->interact(new ConfirmRequest(
        question: "Delete '{$itemName}'?",
        context: 'This action cannot be undone',
        defaultValue: false
    ));

    if ($confirmed) {
        yield from $this->performDeletion();
        $this->trigger('deleted');
    } else {
        $this->trigger('cancelled');
    }
})
```

**CLI Output**:
```
Delete 'user_data.db'? This action cannot be undone (y/N): y
✓ Deleted user_data.db
```

---

### 2. Single Selection

**Scenario**: Choose one option from a list

```php
->onAction('select_backend', function(object $t): \Generator {
    $backend = yield from $this->interact(new SelectRequest(
        question: 'Choose storage backend',
        options: [
            'mysql' => new SelectOption(
                label: 'MySQL',
                description: 'Traditional relational database'
            ),
            'postgres' => new SelectOption(
                label: 'PostgreSQL',
                description: 'Advanced features, better performance'
            ),
            'mongodb' => new SelectOption(
                label: 'MongoDB',
                description: 'Document-oriented, schema-flexible'
            ),
        ],
        defaultKey: 'postgres'
    ));

    $this->set('storage_backend', $backend);
    echo "Selected: {$backend}\n";
})
```

**CLI Output**:
```
? Choose storage backend (Use arrow keys)
  > PostgreSQL - Advanced features, better performance
    MySQL - Traditional relational database
    MongoDB - Document-oriented, schema-flexible

✓ Selected: postgres
```

---

### 3. Multiple Selection

**Scenario**: Choose multiple items (checkboxes)

```php
->onAction('select_features', function(object $t): \Generator {
    $features = yield from $this->interact(new ChoiceRequest(
        question: 'Which features do you want to enable?',
        options: [
            'caching' => new ChoiceOption(
                label: 'Caching Layer',
                description: 'Redis-backed caching for performance'
            ),
            'auth' => new ChoiceOption(
                label: 'Authentication',
                description: 'JWT-based user authentication',
                recommended: true
            ),
            'logging' => new ChoiceOption(
                label: 'Structured Logging',
                description: 'JSON logging with log levels'
            ),
            'metrics' => new ChoiceOption(
                label: 'Metrics Collection',
                description: 'Prometheus metrics endpoint'
            ),
        ],
        minSelections: 1,
        maxSelections: null,
        defaultKeys: ['auth']
    ));

    $this->set('enabled_features', $features);
    echo "Enabled: " . implode(', ', $features) . "\n";
})
```

**CLI Output**:
```
? Which features do you want to enable? (Space to select, Enter to confirm)
  ◯ Caching Layer - Redis-backed caching for performance
  ◉ Authentication - JWT-based user authentication ⭐
  ◉ Structured Logging - JSON logging with log levels
  ◯ Metrics Collection - Prometheus metrics endpoint

✓ Enabled: auth, logging
```

---

### 4. Free Text Input

**Scenario**: Gather text input from user

```php
->onAction('get_description', function(object $t): \Generator {
    $description = yield from $this->interact(new PromptRequest(
        question: 'Describe the task',
        placeholder: 'e.g., "Process CSV files from uploads folder"',
        multiline: true,
        validation: '^.{10,500}$'  // 10-500 characters
    ));

    $this->set('task_description', $description);
})
```

**CLI Output**:
```
? Describe the task (10-500 characters)
  e.g., "Process CSV files from uploads folder"

> Process all CSV files in the uploads directory,
  validate the data, and import into the database.
  Send email notification on completion.

✓ Task description saved
```

---

## Machine-Agent Scenarios

### Example 1: Iterative Requirements Gathering

**Machine-Agent** gathering requirements for machine generation

```php
// State: gathering_requirements
->onEnter('gathering_requirements', function(object $t): \Generator {
    // Initial description
    $description = yield from $this->interact(new PromptRequest(
        question: 'Describe the state machine you want to create',
        placeholder: 'e.g., "A workflow for order processing with payment integration"',
        multiline: true,
        validation: '^.{20,}$'  // At least 20 characters
    ));

    $this->set('user_request', $description);
    $this->trigger('description_received');
})

// State: questioning
->onEnter('questioning', function(object $t): \Generator {
    $confidence = $this->get('confidence_score', 0.0);
    $questions = $this->get('remaining_questions', []);

    while ($confidence < 0.75 && !empty($questions)) {
        $question = array_shift($questions);

        $answer = yield from $this->interact(new PromptRequest(
            question: $question['text'],
            context: "Confidence: " . round($confidence * 100) . "%",
            multiline: $question['multiline'] ?? false
        ));

        // Store answer
        $qaHistory = $this->get('qa_history', []);
        $qaHistory[] = [
            'question' => $question['text'],
            'answer' => $answer,
            'timestamp' => time()
        ];
        $this->set('qa_history', $qaHistory);

        // Recalculate confidence (via AI)
        $confidence = yield from $this->calculateConfidence();
        $this->set('confidence_score', $confidence);
    }

    $this->trigger('requirements_complete');
})
```

---

### Example 2: Configuration Workflow

**Machine-Agent** configuring generated machine

```php
->onEnter('configuring_machine', function(object $t): \Generator {
    // 1. Choose complexity
    $complexity = yield from $this->interact(new SelectRequest(
        question: 'What complexity level is appropriate?',
        options: [
            'simple' => new SelectOption('Simple', '1-3 states, basic flow'),
            'moderate' => new SelectOption('Moderate', '4-8 states, some branching'),
            'complex' => new SelectOption('Complex', '9+ states, hierarchical'),
        ],
        context: 'Based on: ' . $this->get('user_request')
    ));
    $this->set('machine_complexity', $complexity);

    // 2. Select features
    $features = yield from $this->interact(new ChoiceRequest(
        question: 'Which features should I include?',
        options: $this->getRelevantFeatureOptions(),
        minSelections: 0,
        context: "Complexity: {$complexity}"
    ));
    $this->set('required_features', $features);

    // 3. Choose AI model for generation
    $model = yield from $this->interact(new SelectRequest(
        question: 'Which AI model should generate the code?',
        options: [
            'claude-sonnet' => new SelectOption(
                'Claude Sonnet 4',
                'Highest quality, slower, expensive'
            ),
            'qwen-coder' => new SelectOption(
                'Qwen Coder 7B',
                'Good quality, fast, local',
                recommended: true
            ),
        ],
        defaultKey: 'qwen-coder'
    ));
    $this->set('generation_model', $model);

    // 4. Confirm before generation
    $proceed = yield from $this->interact(new ConfirmRequest(
        question: 'Proceed with machine generation?',
        context: $this->buildSummary(),
        defaultValue: true
    ));

    if ($proceed) {
        $this->trigger('start_generation');
    } else {
        $this->trigger('reconfigure');
    }
})

private function buildSummary(): string
{
    return sprintf(
        "Complexity: %s\nFeatures: %s\nModel: %s",
        $this->get('machine_complexity'),
        implode(', ', $this->get('required_features', [])),
        $this->get('generation_model')
    );
}
```

---

## Multi-Agent Coordination

### Example 3: Supervisor-Worker Approval Flow

**Worker agent** requests approval from **supervisor agent**

```php
// Worker Machine (requests approval)
->onEnter('pending_approval', function(object $t): \Generator {
    $operation = $this->get('planned_operation');

    $approved = yield from $this->interact(new ConfirmRequest(
        question: "Execute {$operation['name']}?",
        context: json_encode([
            'operation' => $operation,
            'risk_level' => $this->assessRisk($operation),
            'estimated_cost' => $this->estimateCost($operation),
        ]),
        timeoutMs: 30000  // 30 second timeout
    ));

    if ($approved) {
        yield from $this->executeOperation($operation);
    } else {
        $this->trigger('denied');
    }
})

// Supervisor Machine (provides approval)
// Listens for approval requests and applies policy
class SupervisorApprovalAgent
{
    private PolicyEngine $policy;

    public function __construct(Region $supervisorRegion, PolicyEngine $policy)
    {
        $this->policy = $policy;

        // Subscribe to all ConfirmRequest events
        $supervisorRegion->on(
            function(ConfirmRequest $request, ?Region $worker) {
                $this->handleApprovalRequest($request, $worker);
            }
        );
    }

    private function handleApprovalRequest(
        ConfirmRequest $request,
        Region $worker
    ): void {
        $context = json_decode($request->context ?? '{}', true);

        // Apply policy rules
        $decision = $this->policy->evaluate(
            operation: $context['operation'] ?? [],
            riskLevel: $context['risk_level'] ?? 'unknown',
            cost: $context['estimated_cost'] ?? 0
        );

        echo sprintf(
            "[SUPERVISOR] %s request from worker: %s\n",
            $decision['approved'] ? 'APPROVED' : 'DENIED',
            $request->question
        );

        // Send response back to worker
        $response = $request->createResponse(
            ConfirmResponse::class,
            ['confirmed' => $decision['approved'], 'cancelled' => false]
        );

        $worker->notificationChain->call(new Notify($worker, $response));
    }
}

// Usage
$supervisor = $supervisorBuilder->build();
$worker = $workerBuilder->build();

new SupervisorApprovalAgent($supervisor, new PolicyEngine([
    'max_cost' => 1000,
    'allowed_risk_levels' => ['low', 'medium'],
]));

$worker->run();  // Worker will request approval, supervisor auto-responds
```

---

### Example 4: Agent Negotiation

**Multiple agents** negotiate resource allocation

```php
// Coordinator Agent
->onAction('allocate_resources', function(object $t): \Generator {
    $agents = $this->get('worker_agents');
    $availableResources = $this->get('resource_pool');

    foreach ($agents as $agent) {
        // Ask each agent what resources they need
        $needs = yield from $this->interact(new ChoiceRequest(
            question: "Agent {$agent->id}: Which resources do you need?",
            options: $this->buildResourceOptions($availableResources),
            minSelections: 1,
            maxSelections: 3,
            context: json_encode(['agent_id' => $agent->id])
        ));

        // Allocate resources
        foreach ($needs as $resourceKey) {
            $this->allocate($agent, $availableResources[$resourceKey]);
        }
    }

    $this->trigger('allocation_complete');
})

// Worker Agent (responds to resource requests)
class WorkerResourceResponder
{
    public function __construct(
        private Region $workerRegion,
        private array $resourcePreferences
    ) {
        $workerRegion->on(function(ChoiceRequest $req, Region $coordinator) {
            $context = json_decode($req->context ?? '{}', true);

            if (($context['agent_id'] ?? null) === $this->workerRegion->id) {
                $this->respondToResourceRequest($req, $coordinator);
            }
        });
    }

    private function respondToResourceRequest(
        ChoiceRequest $request,
        Region $coordinator
    ): void {
        // Select resources based on preferences
        $selectedKeys = array_intersect(
            array_keys($request->options),
            $this->resourcePreferences
        );

        $response = $request->createResponse(
            ChoiceResponse::class,
            ['selectedKeys' => array_values($selectedKeys), 'cancelled' => false]
        );

        $coordinator->notificationChain->call(
            new Notify($coordinator, $response)
        );
    }
}
```

---

## CLI Workflows

### Example 5: Interactive Deployment Pipeline

```php
// Holon: deployment-pipeline

states:
  idle: {}

  preparing:
    onEnter: !callback prepare_deployment

  confirming:
    onEnter: !callback confirm_deployment

  deploying:
    onEnter: !callback execute_deployment

  finished: {}

transitions:
  - from: idle
    to: preparing
    event: start

  - from: preparing
    to: confirming
    event: prepared

  - from: confirming
    to: deploying
    event: confirmed

  - from: confirming
    to: idle
    event: cancelled

  - from: deploying
    to: finished
    event: deployed

callbacks:
  prepare_deployment: !php |
    function(): \Generator {
        echo "Preparing deployment...\n";

        // Build artifacts
        yield from $this->buildArtifacts();

        // Run tests
        $testResults = yield from $this->runTests();

        $this->set('test_results', $testResults);
        $this->set('artifacts_ready', true);

        $this->trigger('prepared');
    }

  confirm_deployment: !php |
    function(): \Generator {
        $testResults = $this->get('test_results');

        $summary = sprintf(
            "Tests: %d passed, %d failed\nArtifacts: Ready\nTarget: Production",
            $testResults['passed'],
            $testResults['failed']
        );

        $confirmed = yield from $this->interact(new ConfirmRequest(
            question: 'Deploy to production?',
            context: $summary,
            defaultValue: $testResults['failed'] === 0
        ));

        if ($confirmed) {
            $this->trigger('confirmed');
        } else {
            echo "Deployment cancelled.\n";
            $this->trigger('cancelled');
        }
    }

  execute_deployment: !php |
    function(): \Generator {
        echo "Deploying...\n";

        yield from $this->deploy();

        echo "✓ Deployment successful!\n";
        $this->trigger('deployed');
    }
```

**Execution**:
```bash
$ php run-deployment.php

Preparing deployment...
Running tests... ✓
Building artifacts... ✓

? Deploy to production?
  Tests: 47 passed, 0 failed
  Artifacts: Ready
  Target: Production
  (Y/n): y

Deploying...
✓ Deployment successful!
```

---

### Example 6: Interactive Configuration Wizard

```php
// Configuration wizard for new project setup

->onEnter('wizard_start', function(object $t): \Generator {
    echo "\n=== Project Setup Wizard ===\n\n";

    // 1. Project name
    $projectName = yield from $this->interact(new PromptRequest(
        question: 'Project name',
        placeholder: 'my-awesome-project',
        validation: '^[a-z0-9-]+$'
    ));
    $this->set('project_name', $projectName);

    // 2. Project type
    $projectType = yield from $this->interact(new SelectRequest(
        question: 'Project type',
        options: [
            'web' => new SelectOption('Web Application', 'Full-stack web app'),
            'api' => new SelectOption('REST API', 'Backend API service'),
            'cli' => new SelectOption('CLI Tool', 'Command-line application'),
            'library' => new SelectOption('Library', 'Reusable package'),
        ]
    ));
    $this->set('project_type', $projectType);

    // 3. Language/Framework (dynamic based on type)
    $framework = yield from $this->interact(new SelectRequest(
        question: 'Framework',
        options: $this->getFrameworkOptions($projectType)
    ));
    $this->set('framework', $framework);

    // 4. Features (multi-select)
    $features = yield from $this->interact(new ChoiceRequest(
        question: 'Additional features',
        options: [
            'docker' => new ChoiceOption('Docker', 'Containerization', true),
            'ci' => new ChoiceOption('CI/CD', 'GitHub Actions workflow', true),
            'testing' => new ChoiceOption('Testing', 'PHPUnit/Jest setup', true),
            'linting' => new ChoiceOption('Linting', 'Code quality tools'),
            'docs' => new ChoiceOption('Documentation', 'Auto-generated docs'),
        ],
        minSelections: 0
    ));
    $this->set('features', $features);

    // 5. Review and confirm
    $this->displaySummary();

    $confirmed = yield from $this->interact(new ConfirmRequest(
        question: 'Create project with these settings?',
        defaultValue: true
    ));

    if ($confirmed) {
        yield from $this->createProject();
    } else {
        echo "Setup cancelled.\n";
    }
})
```

---

## Web Application Integration

### Example 7: REST API for Pending Interactions

**Backend: State machine with interactions**

```php
// Order processing machine with approval step

->onEnter('pending_approval', function(object $t): \Generator {
    $order = $this->get('order');

    $approved = yield from $this->interact(new ConfirmRequest(
        question: "Approve order #{$order['id']}?",
        context: json_encode([
            'customer' => $order['customer'],
            'total' => $order['total'],
            'items' => $order['items'],
        ]),
        timeoutMs: 300000  // 5 minutes
    ));

    if ($approved) {
        yield from $this->processOrder();
    } else {
        $this->trigger('rejected');
    }
})
```

**API Controller**:

```php
class OrderMachineController
{
    private WebInteractionAdapter $adapter;

    public function __construct(Region $orderMachine)
    {
        $this->adapter = new WebInteractionAdapter($orderMachine);
    }

    /**
     * GET /orders/{id}/interactions/pending
     */
    public function getPendingInteractions(string $orderId): JsonResponse
    {
        $pending = $this->adapter->getPending();

        return new JsonResponse([
            'interactions' => array_map(
                fn($i) => $this->formatInteraction($i),
                $pending
            )
        ]);
    }

    /**
     * POST /orders/{id}/interactions/{correlationId}/respond
     */
    public function respondToInteraction(
        string $orderId,
        string $correlationId,
        Request $request
    ): JsonResponse {
        $data = $request->json()->all();

        try {
            $this->adapter->respond($correlationId, $data);

            return new JsonResponse(['status' => 'accepted'], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function formatInteraction(array $interaction): array
    {
        $request = $interaction['request'];

        return [
            'id' => $request->correlationId(),
            'type' => $request->getType(),
            'question' => $request->question,
            'context' => json_decode($request->context ?? '{}'),
            'timestamp' => $interaction['timestamp'],
            'status' => $interaction['status'],
            // Type-specific fields
            ...$this->getTypeSpecificFields($request)
        ];
    }

    private function getTypeSpecificFields(InteractionRequest $request): array
    {
        return match($request->getType()) {
            'confirm' => [
                'defaultValue' => $request->defaultValue,
            ],
            'select' => [
                'options' => array_map(
                    fn($opt) => [
                        'label' => $opt->label,
                        'description' => $opt->description
                    ],
                    $request->options
                ),
                'defaultKey' => $request->defaultKey,
            ],
            // ... other types
            default => []
        };
    }
}
```

**Frontend: React Component**

```tsx
// OrderApprovalWidget.tsx
import { useState, useEffect } from 'react';

interface PendingInteraction {
  id: string;
  type: 'confirm' | 'select' | 'choice' | 'prompt';
  question: string;
  context: any;
  // ... type-specific fields
}

export function OrderApprovalWidget({ orderId }: { orderId: string }) {
  const [interactions, setInteractions] = useState<PendingInteraction[]>([]);

  useEffect(() => {
    const fetchPending = async () => {
      const res = await fetch(`/api/orders/${orderId}/interactions/pending`);
      const data = await res.json();
      setInteractions(data.interactions);
    };

    fetchPending();
    const interval = setInterval(fetchPending, 5000); // Poll every 5s
    return () => clearInterval(interval);
  }, [orderId]);

  const handleConfirm = async (interactionId: string, confirmed: boolean) => {
    await fetch(`/api/orders/${orderId}/interactions/${interactionId}/respond`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ confirmed, cancelled: false })
    });

    // Remove from pending
    setInteractions(prev => prev.filter(i => i.id !== interactionId));
  };

  return (
    <div className="pending-interactions">
      <h3>Pending Approvals</h3>
      {interactions.map(interaction => (
        <div key={interaction.id} className="interaction-card">
          <p>{interaction.question}</p>
          {interaction.context && (
            <pre>{JSON.stringify(interaction.context, null, 2)}</pre>
          )}

          {interaction.type === 'confirm' && (
            <div className="actions">
              <button onClick={() => handleConfirm(interaction.id, true)}>
                Approve
              </button>
              <button onClick={() => handleConfirm(interaction.id, false)}>
                Reject
              </button>
            </div>
          )}

          {/* Handle other interaction types */}
        </div>
      ))}
    </div>
  );
}
```

---

## Advanced Patterns

### Example 8: Nested Interactions with Context

```php
->onAction('configure_database', function(object $t): \Generator {
    // 1. Choose database type
    $dbType = yield from $this->interact(new SelectRequest(
        question: 'Choose database type',
        options: [
            'mysql' => new SelectOption('MySQL'),
            'postgres' => new SelectOption('PostgreSQL'),
            'mongodb' => new SelectOption('MongoDB'),
        ]
    ));

    $this->set('db_type', $dbType);

    // 2. Nested: If MySQL, ask about version
    if ($dbType === 'mysql') {
        $version = yield from $this->interact(new SelectRequest(
            question: 'MySQL version',
            options: [
                '8.0' => new SelectOption('MySQL 8.0', 'Latest stable'),
                '5.7' => new SelectOption('MySQL 5.7', 'Legacy support'),
            ],
            defaultKey: '8.0'
        ));

        $this->set('db_version', $version);
    }

    // 3. Nested: Confirm configuration
    $summary = $this->buildDatabaseSummary();

    $confirmed = yield from $this->interact(new ConfirmRequest(
        question: 'Use this database configuration?',
        context: $summary,
        defaultValue: true
    ));

    if (!$confirmed) {
        // Start over
        yield from $this->trigger('reconfigure');
    }
})
```

---

### Example 9: Conditional Interaction Chains

```php
->onAction('deployment_workflow', function(object $t): \Generator {
    $environment = yield from $this->interact(new SelectRequest(
        question: 'Deploy to which environment?',
        options: [
            'dev' => new SelectOption('Development', 'Auto-deploy, no approval'),
            'staging' => new SelectOption('Staging', 'Team review required'),
            'production' => new SelectOption('Production', 'Full approval process'),
        ]
    ));

    $this->set('target_environment', $environment);

    // Conditional approval based on environment
    if ($environment === 'production') {
        // Multi-step approval for production
        $teamApproved = yield from $this->interact(new ConfirmRequest(
            question: 'Team lead approval?',
            context: 'Production deployment requires team lead sign-off'
        ));

        if (!$teamApproved) {
            $this->trigger('deployment_denied');
            return;
        }

        $managementApproved = yield from $this->interact(new ConfirmRequest(
            question: 'Management approval?',
            context: 'Final approval required for production'
        ));

        if (!$managementApproved) {
            $this->trigger('deployment_denied');
            return;
        }

        // Select maintenance window
        $maintenanceWindow = yield from $this->interact(new SelectRequest(
            question: 'Deployment window?',
            options: [
                'immediate' => new SelectOption('Immediate', 'Deploy now'),
                'tonight' => new SelectOption('Tonight 2 AM', 'Off-peak'),
                'weekend' => new SelectOption('This Weekend', 'Lowest traffic'),
            ]
        ));

        $this->set('maintenance_window', $maintenanceWindow);
    }

    $this->trigger('deployment_approved');
})
```

---

### Example 10: Timeout Handling

```php
->onAction('request_with_timeout', function(object $t): \Generator {
    try {
        $response = yield from $this->interact(new ConfirmRequest(
            question: 'Approve urgent request?',
            context: 'Requires immediate attention',
            timeoutMs: 30000  // 30 seconds
        ));

        if ($response) {
            yield from $this->executeUrgentAction();
        }
    } catch (InteractionCancelledException $e) {
        // Timeout or user cancelled
        echo "Request timed out. Using fallback.\n";

        yield from $this->executeFallbackAction();
    }
})
```

---

## Summary

These examples demonstrate:

✅ **Basic patterns** - Confirm, Select, Choice, Prompt
✅ **Machine-Agent workflows** - Requirements gathering, configuration
✅ **Multi-agent coordination** - Supervisor approval, resource negotiation
✅ **CLI integration** - Interactive wizards, deployment pipelines
✅ **Web integration** - REST API, real-time polling
✅ **Advanced patterns** - Nested interactions, conditional flows, timeout handling

**Next Steps**:
- Review [architecture.md](./architecture.md) for technical details
- See [interaction-patterns.md](./interaction-patterns.md) for full specification
- Explore [README.md](./README.md) for overview and roadmap

---

**Last Updated**: 2026-01-03
