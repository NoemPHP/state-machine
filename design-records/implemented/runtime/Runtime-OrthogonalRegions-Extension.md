# Runtime - Orthogonal Regions Extension

**Status**: Implemented
**Created**: 2025-12-14
**Related**: [Runtime.md](./Runtime.md)

## Overview

Extends OrthogonalRegions feature with `$this->summon()` method for declarative runtime spawning, solving the external context problem.

## Problem: External Context Breaks Declarative Nature

### Current Proposal (Broken)

```php
$parentRuntime = new StandardRuntime($parentRegion);

$builder->state('process_document')
    ->onEnter(function($trigger) use ($parentRuntime) {  // ❌ Requires external context
        $childRuntime = $parentRuntime->spawn(...);
    });
```

**Fatal issues**:
1. ❌ Requires `use($parentRuntime)` - impossible in YAML definitions
2. ❌ Breaks isolated Region configs (no closure scope available)
3. ❌ Violates declarative principle - state machines should be self-contained
4. ❌ Coupling between execution context and machine definition

### Desired Declarative API

```php
$builder->state('process_document')
    ->onEnter(function($trigger) {
        // ✅ Works like $this->get(), $this->set(), $this->dispatch()
        $childRuntime = $this->summon(
            builder: match($trigger->type) {
                'json' => new JsonParserBuilder(),
                'xml' => new XmlParserBuilder(),
            },
            shareMesh: true,
        );

        $childRuntime->run();
    });
```

**In YAML**:
```yaml
states:
  - name: process_document
    onEnter: !php |
      $childRuntime = $this->summon(
        builder: new JsonParserBuilder(),
        shareMesh: true,
      );
      $childRuntime->run();
```

✅ No external context required!

---

## Solution: Extend OrthogonalRegions

OrthogonalRegions already manages multiple regions. Extend it to provide runtime spawning capability.

### Why OrthogonalRegions?

**Conceptual fit**:
- Already deals with managing child regions
- Understands hierarchical region relationships
- Natural place for "create and manage sub-region" functionality

**Technical fit**:
- Already hooks into BoundAccess chain (could add methods)
- Already works with ConnectedRegions chain
- Natural evolution of region composition

### Architecture

```
OrthogonalRegions Feature
    ├── Existing: Static sub-region support (YAML 'regions:' key)
    └── NEW: Dynamic runtime spawning ($this->summon())
```

---

## Implementation Design

### 1. Runtime Stores Reference to Itself

**StandardRuntime constructor**:

```php
class StandardRuntime implements Runtime
{
    public function __construct(
        private readonly Region $region,
        private readonly RuntimeConfig $config = new RuntimeConfig(),
    ) {
        // Store runtime reference in Region metadata
        $this->registerRuntimeReference();

        // Initialize context sharing if requested
        if ($this->config->parentMesh) {
            $this->initializeContextSharing();
        }
    }

    /**
     * Store this Runtime instance in Region metadata
     * Enables OrthogonalRegions to access it for summon()
     */
    private function registerRuntimeReference(): void
    {
        $chainMail = $this->region->getChainMail();
        $metaChain = $chainMail->get(Chains\Meta::class);

        if ($metaChain) {
            $metaParams = new Chains\Params\Meta(
                $this->region,
                RuntimeMetaType::get()
            );

            $mesh = $metaChain->call($metaParams);
            $mesh['runtime'] = $this;  // Store runtime reference
        }
    }
}
```

### 2. RuntimeMetaType Identifier

```php
<?php

declare(strict_types=1);

namespace Noem\State;

/**
 * Metadata type identifier for Runtime references
 */
class RuntimeMetaType
{
    private static ?RuntimeMetaType $instance = null;

    private function __construct() {}

    public static function get(): RuntimeMetaType
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __toString(): string
    {
        return 'runtime';
    }
}
```

### 3. Extend OrthogonalRegions Feature

```php
<?php

declare(strict_types=1);

namespace Noem\State\Feature\OrthogonalRegions;

use Nette\Schema\Elements\Type;
use Nette\Schema\Expect;
use Noem\State\Chains;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\OrthogonalRegions\RegionChains\ParentRegion;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use Noem\State\RuntimeMetaType;

class OrthogonalRegions implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(
            fn(Chains\ConnectedRegions $connectedRegions): ParentRegion =>
                new ParentRegion($connectedRegions),
        )->use(
            function (
                ?LoaderChains\Schema $schema,
                Chains\ConnectedRegions $connectedRegions,
                Chains\Get $get,
                Chains\Set $set,
                ParentRegion $parentRegion,
                BoundAccess $boundAccess,      // NEW: Add BoundAccess
                Chains\Meta $meta,              // NEW: Add Meta
            ) {
                /**
                 * EXISTING: Extend region schema for static 'regions' support
                 */
                $schema?->link(function (SchemaContext $context, callable $next) {
                    $nestedRegion = new Type('list');
                    $context->state = $context->state->extend([
                        'regions' => $nestedRegion,
                    ]);
                    $context->region = $context->region->extend([
                        'states' => Expect::listOf($context->state),
                    ]);
                    $nestedRegion->items($context->region);

                    return $next($context);
                });

                /**
                 * NEW: Add summon() method for dynamic runtime spawning
                 */
                $boundAccess->link(function (BoundAccessParams $params, callable $next) use ($meta) {
                    if ($params->type !== BoundAccessParams::TYPE_METHOD) {
                        return $next($params);
                    }

                    if ($params->name !== 'summon') {
                        return $next($params);
                    }

                    // Extract parameters
                    $args = $params->payload;
                    $builder = $args['builder'] ?? $args[0] ?? null;
                    $shareMesh = $args['shareMesh'] ?? $args[1] ?? false;
                    $shareContainer = $args['shareContainer'] ?? $args[2] ?? false;
                    $config = $args['config'] ?? $args[3] ?? null;

                    if (!$builder instanceof RegionBuilder) {
                        throw new \RuntimeException(
                            'summon() requires RegionBuilder as first parameter. ' .
                            'Example: $this->summon(builder: new MyBuilder())'
                        );
                    }

                    // Retrieve parent Runtime from metadata
                    $metaParams = new Chains\Params\Meta(
                        $params->region,
                        RuntimeMetaType::get()
                    );
                    $runtimeMesh = $meta->call($metaParams);
                    $parentRuntime = $runtimeMesh['runtime'] ?? null;

                    if (!$parentRuntime) {
                        throw new \RuntimeException(
                            'summon() can only be called from within a Runtime context. ' .
                            'Wrap your Region in StandardRuntime first: ' .
                            '$runtime = new StandardRuntime($region); $runtime->run();'
                        );
                    }

                    // Build child region
                    $childRegion = $builder->build();

                    // Spawn child runtime using parent's spawn() method
                    return $parentRuntime->spawn(
                        childRegion: $childRegion,
                        shareMesh: $shareMesh,
                        shareContainer: $shareContainer,
                        config: $config,
                    );
                });
            }
        );
    }
}
```

---

## Method Name: `summon()`

### Why "summon"?

**Evocative**: Conjures imagery of calling forth a sub-process or helper
**Clear intent**: Implies creation and invocation
**Poetic**: More memorable than `spawnRuntime()` or `createChild()`
**Concise**: 6 letters, easy to type
**Metaphor**: Summoning a spirit/entity to perform a task

### Alternative Names Considered

| Name | Metaphor | Pros | Cons |
|------|----------|------|------|
| `summon()` | Magical invocation | Poetic, memorable | Might seem whimsical |
| `nest()` | Birds nesting | Hierarchical relationship clear | Less action-oriented |
| `weave()` | Thread integration | Integration metaphor | Less clear for spawning |
| `embark()` | Begin journey | Process metaphor | Implies starting, not creating |
| `invoke()` | Direct call | Simple, clear | Too generic |
| `spawn()` | Process creation | Familiar (Unix) | Already used in Runtime::spawn() |

**Recommendation**: `summon()` - Best balance of poetry and clarity

---

## Usage Patterns

### Basic Summoning

```php
$builder = (new RegionBuilder())
    ->enableFeatures(
        new ExtendedState(),     // Required for $this-> access
        new OrthogonalRegions(), // Provides summon() method
    )
    ->state('process')
        ->onEnter(function($trigger) {
            $childRuntime = $this->summon(
                builder: new ProcessorBuilder(),
                shareMesh: true,
            );

            $childRuntime->run(onComplete: function($result) {
                $this->set('childResult', $result);
            });
        })
    ->build();

// Wrap in Runtime to enable summoning
$runtime = new StandardRuntime($region);
$runtime->run();
```

### YAML Summoning

```yaml
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\OrthogonalRegions\OrthogonalRegions

states:
  - name: process_document
    initial: true
    onEnter: !php |
      $builder = match($trigger->documentType) {
        'json' => new App\JsonParserBuilder(),
        'xml' => new App\XmlParserBuilder(),
        'csv' => new App\CsvParserBuilder(),
      };

      $childRuntime = $this->summon(
        builder: $builder,
        shareMesh: true,
        shareContainer: true,
      );

      $childRuntime->run(onComplete: function($result) {
        $this->set('parsed', $result);
        $this->dispatch(new ParsingComplete($result));
      });

  - name: final
    final: true
```

**✅ Works perfectly in YAML!** No external context needed.

### Dynamic Builder Pattern

```php
->state('dynamic_processor')
    ->onEnter(function($trigger) {
        $childBuilder = (new RegionBuilder())
            ->enableFeatures(new ExtendedState())
            ->state('processing')
                ->onEnter(fn($t) => processData($t))
                ->transition('done')
            ->state('done')
                ->final();

        $childRuntime = $this->summon(
            builder: $childBuilder,
            shareMesh: true,
        );

        // Child shares parent's ExtendedState context
        foreach ($childRuntime->events() as $event) {
            if ($event instanceof Progress) {
                $this->set('progress', $event->percent);
            }
        }

        $childRuntime->run();
    })
```

### Accessing Parent Context in Child

```php
// Parent
->state('parent')
    ->onEnter(function($t) {
        $this->set('sharedConfig', ['timeout' => 5000]);

        $childRuntime = $this->summon(
            builder: new ChildBuilder(),
            shareMesh: true,  // Child can read/write parent context
        );

        $childRuntime->run();

        // Read child's writes (bidirectional!)
        $status = $this->get('childStatus');  // 'processing'
    })

// Child (built by ChildBuilder)
->state('child')
    ->onEnter(function($t) {
        // Read parent's context
        $config = $this->get('sharedConfig');
        $timeout = $config['timeout'];  // 5000

        // Write to shared context (parent can read)
        $this->set('childStatus', 'processing');
    })
```

---

## Feature Loading Requirements

**Critical**: OrthogonalRegions requires ExtendedState!

```php
$builder->enableFeatures(
    new ExtendedState(),     // MUST be first (provides $this-> access)
    new OrthogonalRegions(), // Adds summon() method + static regions
);
```

**Why**: OrthogonalRegions uses BoundAccess chain, which is provided by ExtendedState.

---

## Error Handling

### Runtime Not Wrapped

```php
// Region built but NOT wrapped in Runtime
$region = $builder->build();

// Trigger callback that calls summon()
$region->trigger($event);

// ❌ RuntimeException: "summon() can only be called from within a Runtime context"
```

**Solution**: Wrap in Runtime before running:
```php
$runtime = new StandardRuntime($region);
$runtime->run();  // ✅ Now summon() works
```

**Rationale**: summon() needs parent Runtime to call spawn(). Without Runtime wrapper, there's no parent to retrieve.

### Builder Not Provided

```php
->onEnter(function($t) {
    $childRuntime = $this->summon();  // ❌ No builder
})

// RuntimeException: "summon() requires RegionBuilder as first parameter"
```

**Solution**: Always provide builder:
```php
$childRuntime = $this->summon(builder: new MyBuilder());
```

### ExtendedState Not Loaded

```php
$builder = (new RegionBuilder())
    ->enableFeatures(
        new OrthogonalRegions(),  // ❌ No ExtendedState!
    )
    ->state('parent')
        ->onEnter(fn($t) => $this->summon(...))
    ->build();

// Fatal error: BoundAccess chain not available
```

**Solution**: Load ExtendedState first:
```php
->enableFeatures(
    new ExtendedState(),     // ✅ First
    new OrthogonalRegions(),
)
```

---

## Comparison to Manual Approach

### Before (Runtime-Context-Sharing.md approach)

```php
// Requires external context
$parentRuntime = new StandardRuntime($parentRegion);

$builder->state('process')
    ->onEnter(function($t) use ($parentRuntime) {  // ❌ Can't do this in YAML
        $parentMesh = ExtendedState::getMesh($parentRegion);

        $childRuntime = new StandardRuntime($childRegion, new RuntimeConfig(
            parentMesh: $parentMesh,
        ));
    });
```

**Issues**:
- Requires `use()` closure scope
- Manual Mesh extraction
- Verbose
- Breaks YAML

### After (OrthogonalRegions extension)

```php
// No external context needed
$builder->state('process')
    ->onEnter(function($t) {
        $childRuntime = $this->summon(  // ✅ Works in YAML!
            builder: new ProcessorBuilder(),
            shareMesh: true,
        );
    });
```

**Benefits**:
- Declarative
- Concise
- YAML-compatible
- Consistent with `$this->get()`, `$this->dispatch()` pattern

---

## Testing Strategy

### Unit Tests

```php
class OrthogonalRegionsSummonTest extends TestCase
{
    public function test_summon_available_via_this(): void
    {
        $builder = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new OrthogonalRegions(),
            )
            ->state('parent')
                ->onEnter(function($t) {
                    $childBuilder = (new RegionBuilder())
                        ->state('child')->final();

                    $childRuntime = $this->summon(
                        builder: $childBuilder,
                        shareMesh: true,
                    );

                    $this->assertInstanceOf(Runtime::class, $childRuntime);
                })
            ->build();

        $runtime = new StandardRuntime($builder);
        $runtime->run();
    }

    public function test_summon_throws_when_not_in_runtime_context(): void
    {
        $builder = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new OrthogonalRegions(),
            )
            ->state('parent')
                ->onEnter(function($t) {
                    $this->summon(builder: new RegionBuilder());
                })
            ->build();

        // NOT wrapped in Runtime - should throw
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('can only be called from within a Runtime context');

        $builder->trigger(new \stdClass());
    }

    public function test_summon_shares_mesh_bidirectionally(): void
    {
        $parentBuilder = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new OrthogonalRegions(),
            )
            ->state('parent')
                ->onEnter(function($t) {
                    $this->set('parentData', 'from parent');

                    $childBuilder = (new RegionBuilder())
                        ->enableFeatures(new ExtendedState())
                        ->state('child')
                            ->onEnter(function($ct) {
                                // Read parent's data
                                $parentData = $this->get('parentData');
                                $this->assertSame('from parent', $parentData);

                                // Write child's data
                                $this->set('childData', 'from child');
                            })
                            ->final();

                    $childRuntime = $this->summon(
                        builder: $childBuilder,
                        shareMesh: true,
                    );

                    $childRuntime->run();

                    // Parent reads child's writes (bidirectional!)
                    $childData = $this->get('childData');
                    $this->assertSame('from child', $childData);
                })
            ->build();

        $runtime = new StandardRuntime($parentBuilder);
        $runtime->run();
    }
}
```

### Integration Tests

```php
class OrthogonalRegionsSummonYamlTest extends TestCase
{
    public function test_summon_from_yaml(): void
    {
        $yaml = <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\OrthogonalRegions\OrthogonalRegions

states:
  - name: parent
    initial: true
    onEnter: !php |
      \$childRuntime = \$this->summon(
        builder: new Tests\Support\TestBuilder(),
        shareMesh: true,
      );
      \$childRuntime->run();
      \$this->set('summoned', true);

  - name: final
    final: true
YAML;

        $runtime = Holon::fromYaml($yaml);
        $runtime->run();

        // Verify summoning worked
        $mesh = ExtendedState::getMesh($runtime->getRegion());
        $this->assertTrue($mesh['summoned']);
    }
}
```

---

## Documentation Updates

### ExtendedState CLAUDE.md

Add section:

```markdown
### Using summon() for Dynamic Sub-Runtimes

When OrthogonalRegions feature is loaded, you can dynamically spawn sub-runtimes
that share context with the parent:

\`\`\`php
->state('parent')
    ->onEnter(function($trigger) {
        $this->set('config', ['timeout' => 5000]);

        $childRuntime = $this->summon(
            builder: new ChildBuilder(),
            shareMesh: true,  // Child can access parent's context
        );

        $childRuntime->run();
    })
\`\`\`

The child can read and write parent's context via `$this->get()` and `$this->set()`.
```

### OrthogonalRegions Documentation

Create `src/Feature/OrthogonalRegions/CLAUDE.md`:

```markdown
# OrthogonalRegions Feature

## Purpose

Manages parallel and nested regions within state machines.

**Two modes**:
1. **Static regions**: Declarative sub-regions via YAML `regions:` key
2. **Dynamic summoning**: Runtime sub-region spawning via `$this->summon()`

## Static Regions (YAML)

\`\`\`yaml
states:
  - name: parent
    regions:
      - name: child1
        states: [...]
      - name: child2
        states: [...]
\`\`\`

## Dynamic Summoning (Runtime)

\`\`\`php
->state('parent')
    ->onEnter(function($trigger) {
        $childRuntime = $this->summon(
            builder: new ChildBuilder(),
            shareMesh: true,
        );

        $childRuntime->run();
    })
\`\`\`

### Method Signature

\`\`\`php
$this->summon(
    builder: RegionBuilder,         // Required: Builder for child region
    shareMesh: bool = false,        // Optional: Share ExtendedState context
    shareContainer: bool = false,   // Optional: Share DI container
    config: ?RuntimeConfig = null,  // Optional: Additional runtime config
): Runtime
\`\`\`

### Requirements

- ExtendedState feature must be loaded BEFORE OrthogonalRegions
- Parent region must be wrapped in Runtime (StandardRuntime)
- Builder parameter is required

### Context Sharing

When `shareMesh: true`:
- Child can read parent's context via `$this->get()`
- Child can write to shared context via `$this->set()`
- Parent can read child's writes (bidirectional)

### YAML Support

Works in YAML definitions:

\`\`\`yaml
states:
  - name: parent
    onEnter: !php |
      \$childRuntime = \$this->summon(
        builder: new App\ChildBuilder(),
        shareMesh: true,
      );
      \$childRuntime->run();
\`\`\`

## Error Messages

- "summon() requires RegionBuilder as first parameter" → Provide builder
- "summon() can only be called from within a Runtime context" → Wrap Region in StandardRuntime
- BoundAccess error → Load ExtendedState before OrthogonalRegions
```

---

## Migration from Manual Approach

### Old Code (Runtime-Context-Sharing.md)

```php
$parentMesh = ExtendedState::getMesh($parentRegion);

$childRuntime = new StandardRuntime($childRegion, new RuntimeConfig(
    parentMesh: $parentMesh,
    container: $container,
));
```

### New Code (OrthogonalRegions)

```php
$childRuntime = $this->summon(
    builder: $childBuilder,
    shareMesh: true,
    shareContainer: true,
);
```

**Advantages**:
- No manual Mesh extraction
- Works in YAML
- Shorter, clearer
- Consistent with other `$this->` methods

---

## Summary

**Problem**: `use($parentRuntime)` breaks YAML and isolated configs

**Solution**: Extend OrthogonalRegions with `summon()` method

**How it works**:
1. StandardRuntime stores self in Meta chain (RuntimeMetaType)
2. OrthogonalRegions adds `summon()` to BoundAccess chain
3. Method retrieves parent Runtime from metadata, calls spawn()

**Result**:
✅ Works in YAML
✅ No external context needed
✅ Declarative API
✅ Consistent with `$this->get()`, `$this->dispatch()` pattern
✅ Poetic method name: `summon()`

**Supersedes**: Manual Mesh passing in Runtime-Context-Sharing.md - users should always use `$this->summon()`
