# Holon Spawn Integration Proposal

## Overview
Enable spawning of complete self-contained machines (Holon) instead of just regions.

## Current Limitations
- Spawn only supports regions (no features, no container)
- Spawned regions inherit parent's feature set
- No container isolation options
- No event loop management for spawned machines

## Proposed Changes

### 1. Extend Spawn Schema
Add `machine` property alongside existing `region` property:

```php
// In RegionLoader::extendLoaderSchemaForSpawnerSupport()
$subMachineSpawnerSchema = Expect::structure([
    'guard' => $context->callback,
    'machine' => Expect::anyOf(
        Expect::string(),  // Path to YAML file
        Expect::structure([
            'yaml' => Expect::string()->required(),
            'containerMode' => Expect::anyOf('isolated', 'shared', 'delegating')
                ->default('isolated'),
            'eventLoop' => Expect::structure([
                'managedExternally' => Expect::bool()->default(true),
                'maxIterations' => Expect::int()->default(10000),
            ])->nullable(),
        ])
    ),
    'shared' => Expect::array(),
]);
```

### 2. Create Holon Spawn Step

New method: `RegionLoader::holonSpawnStep()` or `Holon::spawnStep()`

```php
public static function holonSpawnStep(
    string $stateName,
    string|array $machineConfig,  // YAML path or inline config
    callable $guard,
    int $connectionFlags = C::DYNAMIC | C::RECEIVE_EVENTS | C::RECEIVE_ACTIONS,
    string $containerMode = 'isolated'
): BuildStep {
    return new class(
        $stateName,
        $machineConfig,
        $guard,
        $connectionFlags,
        $containerMode
    ) implements BuildStep {
        public function callback(RegionBuilder $builder, callable $next, callable $first): Region {
            $spawnRegistry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
            $parentContainer = $builder->chainMail->invoke(fn(Container $c) => $c);
            $region = $next($builder);

            // Create factory that builds complete machine
            $regionFactory = function() use ($machineConfig, $parentContainer, $containerMode) {
                $options = [
                    'builderArgs' => [],
                ];
                
                // Handle container strategy
                if ($containerMode === 'shared') {
                    $options['builderArgs']['container'] = $parentContainer;
                } elseif ($containerMode === 'delegating') {
                    $options['builderArgs']['container'] = new DelegatingContainer(
                        $parentContainer,
                        $machineConfig['container'] ?? []
                    );
                }
                
                // Build machine using Holon
                if (is_string($machineConfig)) {
                    return Holon::fromYaml($machineConfig, $options);
                } else {
                    $yaml = Yaml::dump($machineConfig);
                    return Holon::fromYaml($yaml, $options);
                }
            };

            $spawnRecord = new RegionSpawnRecord(
                $region,
                $stateName,
                $regionFactory,
                $guard,
                $connectionFlags
            );
            $spawnRegistry->addRecord($spawnRecord);

            return $region;
        }
    };
}
```

### 3. ProcessArray Enhancement

Extend `ProcessArray` to detect `machine` property and use Holon:

```php
// In RegionLoader::processSpawnerSchema()
foreach ($state['spawn'] as $spawnerDefinition) {
    if (isset($spawnerDefinition['machine'])) {
        // Use holonSpawnStep
        $builder->addBuildStep(
            Holon::spawnStep(
                $stateName,
                $spawnerDefinition['machine'],
                $guard,
                $flags,
                $spawnerDefinition['containerMode'] ?? 'isolated'
            )
        );
    } else {
        // Existing region spawn logic
        $builder->addBuildStep(/* ... */);
    }
}
```

### 4. Container Strategies

New class: `DelegatingContainer` for hybrid approach:

```php
class DelegatingContainer implements ContainerInterface {
    public function __construct(
        private ContainerInterface $parent,
        private array $localServices = []
    ) {}
    
    public function get(string $id): mixed {
        // Try local first, fallback to parent
        if (isset($this->localServices[$id])) {
            return $this->localServices[$id];
        }
        return $this->parent->get($id);
    }
    
    public function has(string $id): bool {
        return isset($this->localServices[$id]) || $this->parent->has($id);
    }
}
```

### 5. Event Loop Integration

For spawned machines with event loops, two modes:

**Managed Externally (default)**
- Parent controls ticking
- Spawned machine doesn't run its own loop
- Use normal `Region` return

**Autonomous**
- Spawned machine runs EventLoopManager
- Returns manager instead of region
- Parent tracks manager lifecycle

```php
if ($eventLoopConfig['autonomous'] ?? false) {
    $manager = EventLoopManager::fromYaml($yaml, $options);
    // Start manager in background/coroutine
    return $manager->getRegion();
} else {
    // Normal region return
    return Holon::fromYaml($yaml, $options);
}
```

## Example Usage

### YAML Spawn
```yaml
states:
  - name: idle
    transitions:
      - target: coordinating
  
  - name: coordinating
    spawn:
      # Spawn self-contained worker machine
      - guard: !php "return fn($t) => $t->type === 'heavy_task';"
        machine:
          yaml: "machines/worker/machine.yaml"
          containerMode: delegating
          
      # Inline machine definition
      - guard: !php "return fn($t) => $t->type === 'light_task';"
        machine:
          features:
            - Noem\State\Feature\Transitions\TransitionsFeature
          container:
            services:
              taskConfig:
                value: {timeout: 10}
          states:
            - name: processing
              initial: true
              action:
                - run: !get processTask
              transitions:
                - target: done
            - name: done
              final: true
```

### Programmatic Spawn
```php
$builder->addBuildStep(
    Holon::spawnStep(
        stateName: 'coordinating',
        machineConfig: 'machines/worker/machine.yaml',
        guard: fn($t) => $t->type === 'heavy_task',
        connectionFlags: Connection::DYNAMIC | Connection::RECEIVE_EVENTS,
        containerMode: 'delegating'
    )
);
```

## Benefits

1. **Full machine spawning** - Features, containers, event loops
2. **Container isolation** - Flexible parent/child container strategies
3. **Reusable machines** - Reference external YAML files
4. **Backward compatible** - Existing `region` spawns still work
5. **Composability** - Mix region and machine spawns freely

## Migration Path

1. ✅ No breaking changes - new `machine` property is optional
2. ✅ Existing `region` spawns work as before
3. ✅ Gradual adoption - convert spawns to machines when needed
4. ✅ Clear upgrade path - `region` → `machine` for feature needs

## Open Design Questions

**Note**: These questions are only relevant for spawn integration with parent machines, not for greenfield Holon machines.

### 1. Event Loop Coordination
**Question**: How should parent and spawned event loops coordinate?

**Options**:
- **A. Managed Externally (proposed default)**: Parent controls all ticking, spawned machines are passive regions
  - Pro: Simple, predictable, parent has full control
  - Con: Spawned machine's event loop config ignored
  
- **B. Autonomous**: Spawned machines run their own EventLoopManager in parallel
  - Pro: Spawned machines fully independent
  - Con: Complex synchronization, resource management challenges
  
- **C. Hybrid**: Parent can choose per-spawn whether to manage or let autonomous
  - Pro: Flexibility for different use cases
  - Con: More complex API, multiple coordination modes

**Recommendation**: Start with A (Managed Externally), add B/C if needed.

### 2. Resource Management
**Question**: Should spawned machines be automatically cleaned up?

**Options**:
- **A. Manual cleanup**: User explicitly manages spawned machine lifecycle
  - Pro: Explicit control, no surprises
  - Con: Easy to leak resources
  
- **B. Auto-cleanup on state exit**: When parent exits spawning state, cleanup children
  - Pro: Automatic resource management
  - Con: May prematurely terminate long-running spawns
  
- **C. Reference counting**: Cleanup when no more references exist
  - Pro: Flexible, handles complex scenarios
  - Con: More complex implementation, potential for leaks

**Recommendation**: Start with B (auto-cleanup on exit) with opt-out flag for long-lived spawns.

### 3. Error Propagation
**Question**: How should errors in spawned machines bubble up?

**Options**:
- **A. Silent failure**: Spawned machine errors don't affect parent
  - Pro: Isolation, parent keeps running
  - Con: Silent failures hard to debug
  
- **B. Error events**: Spawned errors dispatched as events to parent
  - Pro: Parent can handle errors gracefully
  - Con: Requires event handling infrastructure
  
- **C. Exception propagation**: Spawned errors throw to parent
  - Pro: Immediate, clear error signaling
  - Con: Can crash parent, less isolation

**Recommendation**: Start with B (error events) for observability without crashing parent.

### 4. Nested Spawning
**Question**: Can spawned machines spawn their own sub-machines?

**Considerations**:
- Depth limits to prevent resource exhaustion
- Container delegation chains (grandparent → parent → child)
- Event propagation through multiple levels
- Cleanup cascades

**Recommendation**: Support nested spawning but document carefully. Add `maxSpawnDepth` configuration.

### 5. Testing Strategy
**Question**: How to mock spawned machines in tests?

**Options**:
- **A. Mock factories**: Replace spawn factory with test double
  - Pro: Full control over spawned behavior
  - Con: Must mock each spawn point
  
- **B. Test mode flag**: Holon has "test mode" that returns mock regions
  - Pro: Automatic mocking of all spawns
  - Con: Less precise control
  
- **C. Dependency injection**: Inject spawn factory via container
  - Pro: Clean separation, testable
  - Con: More boilerplate

**Recommendation**: Support A (mock factories) as it's most flexible and consistent with existing patterns.
