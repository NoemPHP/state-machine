# Runtime - Examples

**Status**: Implemented
**Created**: 2025-12-14
**Related**: [Runtime.md](./Runtime.md)

## Table of Contents

1. [Basic Execution](#basic-execution)
2. [Event Streaming](#event-streaming)
3. [Pipeline Composition](#pipeline-composition)
4. [Dynamic Sub-Runtimes](#dynamic-sub-runtimes)
5. [Async Patterns](#async-patterns)
6. [Container Strategies](#container-strategies)

---

## Basic Execution

### Simple Synchronous Run

```php
use Noem\State\StandardRuntime;
use Noem\State\RuntimeConfig;

// Build your region
$builder = new RegionBuilder();
$builder->enableFeatures(new TransitionsFeature());
// ... configure states ...
$region = $builder->build();

// Create runtime
$runtime = new StandardRuntime($region);

// Execute synchronously
$runtime->run(onComplete: function($result) {
    echo "Final state: {$result->finalState}\n";
});
```

### With Custom Trigger Factory

```php
$runtime = new StandardRuntime($region, new RuntimeConfig(
    maxIterations: 100,
    triggerFactory: function(int $iteration, Region $region) {
        return new class($iteration) {
            public function __construct(public int $tick) {}
        };
    },
    onIteration: function(Region $region, object $trigger, int $iteration) {
        if ($iteration % 10 === 0) {
            echo "Iteration $iteration, current state: {$region->currentState()}\n";
        }
    },
));

$runtime->run();
```

### Using Holon

```php
use Noem\State\Feature\Loader\Holon;

$runtime = Holon::fromYaml('machine.yaml');

// Access region if needed
$region = $runtime->getRegion();

// Execute
$runtime->run(onComplete: function($result) {
    echo "Completed: {$result->status}\n";
});
```

---

## Event Streaming

### Iterating Events

```php
$runtime = new StandardRuntime($region);

// Start execution in background/separate process
// (details depend on async implementation)

// Consume events as they're emitted
foreach ($runtime as $event) {
    match(true) {
        $event instanceof UserLoggedIn => handleLogin($event),
        $event instanceof ItemAddedToCart => updateCart($event),
        $event instanceof OrderPlaced => processOrder($event),
        default => null,
    };
}
```

### Generator Pattern

```php
function processEvents(Runtime $runtime): void
{
    foreach ($runtime->events() as $event) {
        echo "Event: " . get_class($event) . "\n";

        // Conditional processing
        if ($event instanceof CriticalError) {
            logError($event);
            break; // Stop processing
        }

        yield $event; // Can yield to caller if needed
    }
}

$result = processEvents($runtime);
```

### Filtering Events

```php
class EventFilter
{
    public function __construct(
        private Runtime $runtime,
        private string $eventType,
    ) {}

    public function events(): \Generator
    {
        foreach ($this->runtime->events() as $event) {
            if ($event instanceof $this->eventType) {
                yield $event;
            }
        }
    }
}

// Usage
$filter = new EventFilter($runtime, ChunkParsed::class);

foreach ($filter->events() as $chunk) {
    processChunk($chunk);
}
```

---

## Pipeline Composition

### Simple Two-Stage Pipeline

```php
use Noem\State\RuntimePipeline;

// Stage 1: Parse document into chunks
$parserRegion = (new RegionBuilder())
    ->enableFeatures(new TransitionsFeature())
    ->state('reading')
        ->onEnter(fn($t) => readFile($t->filePath))
        ->transition('parsing', fn($t) => $t->fileLoaded)
    ->state('parsing')
        ->onEnter(fn($t) => emitChunks($t))
        ->transition('done')
    ->build();

// Stage 2: Process chunks
$processorRegion = (new RegionBuilder())
    ->enableFeatures(new TransitionsFeature())
    ->state('idle')
        ->transition('processing', fn($t) => $t instanceof Chunk)
    ->state('processing')
        ->onEnter(fn($t) => processChunk($t))
        ->transition('idle')
    ->build();

// Create runtimes
$parser = new StandardRuntime($parserRegion);
$processor = new StandardRuntime($processorRegion);

// Pipe them
(new RuntimePipeline())
    ->pipe($parser)
    ->pipe($processor)
    ->run(onComplete: fn($r) => echo "Pipeline complete!");
```

### Multi-Stage Pipeline with Shared Context

```php
$container = createContainer([
    'logger' => new Logger(),
    'cache' => new Cache(),
]);

$pipeline = new RuntimePipeline();

// Stage 1: Validate
$validator = new StandardRuntime($validatorRegion, new RuntimeConfig(
    container: $container,
    shareContainer: true,
));

// Stage 2: Transform
$transformer = new StandardRuntime($transformerRegion, new RuntimeConfig(
    container: $container,
    shareContainer: true,
));

// Stage 3: Store
$storer = new StandardRuntime($storerRegion, new RuntimeConfig(
    container: $container,
    shareContainer: false, // Last stage doesn't share
));

$pipeline
    ->pipe($validator)
    ->pipe($transformer)
    ->pipe($storer)
    ->run(onComplete: function($result) use ($container) {
        $container->get('logger')->info('Pipeline completed', [
            'result' => $result,
        ]);
    });
```

### Branching Pipeline

```php
class BranchingPipeline
{
    private array $runtimes = [];

    public function pipe(Runtime $runtime, ?callable $condition = null): self
    {
        $this->runtimes[] = ['runtime' => $runtime, 'condition' => $condition];
        return $this;
    }

    public function run(?callable $onComplete = null): void
    {
        foreach ($this->runtimes as $config) {
            $runtime = $config['runtime'];
            $condition = $config['condition'];

            // Wire to previous runtime
            if (count($this->runtimes) > 1) {
                $prev = $this->runtimes[count($this->runtimes) - 2]['runtime'];

                $prev->getRegion()->on(function($event) use ($runtime, $condition) {
                    // Conditional forwarding
                    if (!$condition || $condition($event)) {
                        $runtime->getRegion()->trigger($event);
                    }
                });
            }

            $runtime->run($onComplete);
        }
    }
}

// Usage
(new BranchingPipeline())
    ->pipe($parser)
    ->pipe($jsonProcessor, fn($e) => $e instanceof JsonData)
    ->pipe($xmlProcessor, fn($e) => $e instanceof XmlData)
    ->run();
```

---

## Dynamic Sub-Runtimes

### Document Processing with Format Detection

```php
class DocumentProcessor
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function process(object $trigger): void
    {
        // Detect format
        $format = $this->detectFormat($trigger->filePath);

        // Build appropriate sub-region
        $subBuilder = match($format) {
            'json' => $this->createJsonParser(),
            'xml' => $this->createXmlParser(),
            'csv' => $this->createCsvParser(),
            default => throw new \RuntimeException("Unsupported format: $format"),
        };

        $subRegion = $subBuilder->build();

        // Create isolated sub-runtime
        $subRuntime = new StandardRuntime($subRegion, new RuntimeConfig(
            container: $this->container,
            shareContainer: false, // Isolated context
        ));

        // Execute and collect results
        $results = [];

        foreach ($subRuntime->events() as $event) {
            if ($event instanceof DataParsed) {
                $results[] = $event->data;
            }
        }

        $subRuntime->run(onComplete: function($result) use ($results) {
            echo "Parsed " . count($results) . " records\n";
        });
    }

    private function detectFormat(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    private function createJsonParser(): RegionBuilder
    {
        $builder = new RegionBuilder();
        // ... configure JSON parser states ...
        return $builder;
    }

    // ... other parsers ...
}

// Usage in state machine
$builder->state('process_file')
    ->onEnter(function($trigger) use ($container) {
        $processor = new DocumentProcessor($container);
        $processor->process($trigger);
    });
```

### Parallel Sub-Runtimes

```php
use Amp\Future;

class ParallelProcessor
{
    public function processItems(array $items, callable $createRuntime): array
    {
        $futures = [];

        foreach ($items as $item) {
            // Create runtime for each item
            $runtime = $createRuntime($item);

            // Run each in parallel
            $futures[] = Amp\async(function() use ($runtime) {
                $result = null;
                $runtime->run(onComplete: function($r) use (&$result) {
                    $result = $r;
                });
                return $result;
            });
        }

        // Wait for all to complete
        return Future\await($futures);
    }
}

// Usage
$processor = new ParallelProcessor();

$results = $processor->processItems(
    items: $documents,
    createRuntime: function($document) {
        $builder = new RegionBuilder();
        // ... configure for this document ...
        $region = $builder->build();
        return new StandardRuntime($region);
    }
);

echo "Processed " . count($results) . " documents\n";
```

### Nested Sub-Runtimes with Event Forwarding

```php
$builder->state('parent_state')
    ->onEnter(function($trigger, $parentRegion) {
        // Create child runtime
        $childRegion = (new RegionBuilder())
            ->state('child_working')
                ->onEnter(fn($t) => doWork($t))
                ->transition('child_done')
            ->build();

        $childRuntime = new StandardRuntime($childRegion);

        // Forward child events to parent
        $childRegion->on(function($event) use ($parentRegion) {
            if ($event instanceof Progress) {
                // Forward progress events
                $parentRegion->trigger($event);
            }
        });

        // Run child
        $childRuntime->run(onComplete: function($result) use ($parentRegion) {
            // Notify parent of completion
            $parentRegion->trigger(new ChildComplete($result));
        });
    });
```

---

## Async Patterns

### Amp Integration

```php
use Amp\Future;

function runAsync(Runtime $runtime): Future
{
    return Amp\async(function() use ($runtime) {
        $result = null;

        $runtime->run(onComplete: function($r) use (&$result) {
            $result = $r;
        });

        return $result;
    });
}

// Usage
$runtime1 = new StandardRuntime($region1);
$runtime2 = new StandardRuntime($region2);

// Run both concurrently
$future1 = runAsync($runtime1);
$future2 = runAsync($runtime2);

// Wait for both
[$result1, $result2] = Future\await([$future1, $future2]);
```

### ReactPHP Integration

```php
use React\EventLoop\Loop;

class ReactRuntime
{
    public function __construct(
        private Runtime $runtime,
    ) {}

    public function run(?callable $onComplete = null): void
    {
        Loop::futureTick(function() use ($onComplete) {
            $this->runtime->run($onComplete);
        });
    }

    public function runPeriodic(float $interval, callable $onTick): void
    {
        Loop::addPeriodicTimer($interval, function() use ($onTick) {
            $onTick($this->runtime);
        });
    }
}

// Usage
$reactRuntime = new ReactRuntime($runtime);

$reactRuntime->run(onComplete: function($result) {
    echo "Async complete: " . $result->value . "\n";
    Loop::stop();
});

Loop::run();
```

### Event Streaming with Async Iteration

```php
use Amp\Pipeline\Pipeline;

function streamEvents(Runtime $runtime): Pipeline
{
    return Pipeline\fromIterable(function() use ($runtime) {
        foreach ($runtime->events() as $event) {
            yield $event;
        }
    });
}

// Usage
$pipeline = streamEvents($runtime);

// Concurrent consumption
$future = Amp\async(function() use ($pipeline) {
    foreach ($pipeline as $event) {
        // Process event asynchronously
        Amp\delay(0.1); // Simulate async work
        handleEvent($event);
    }
});

$future->await();
```

---

## Container Strategies

### Isolated Containers

```php
// Each runtime gets its own container
$container1 = createContainer(['db' => new Database('conn1')]);
$container2 = createContainer(['db' => new Database('conn2')]);

$runtime1 = new StandardRuntime($region1, new RuntimeConfig(
    container: $container1,
    shareContainer: false,
));

$runtime2 = new StandardRuntime($region2, new RuntimeConfig(
    container: $container2,
    shareContainer: false,
));

// Runtimes are completely independent
```

### Shared Container

```php
// Both runtimes share the same container
$sharedContainer = createContainer([
    'logger' => new Logger(),
    'cache' => new Cache(),
]);

$runtime1 = new StandardRuntime($region1, new RuntimeConfig(
    container: $sharedContainer,
    shareContainer: true,
));

$runtime2 = new StandardRuntime($region2, new RuntimeConfig(
    container: $sharedContainer,
    shareContainer: true,
));

// Both can access the same logger, cache, etc.
```

### Container Forking Pattern

```php
class ForkableContainer implements ContainerInterface
{
    private array $services = [];
    private array $singletons = [];

    public function fork(): self
    {
        $forked = clone $this;
        $forked->singletons = []; // Clear singleton cache
        return $forked;
    }

    public function get(string $id): mixed
    {
        if (isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }

        $service = $this->services[$id]($this);
        $this->singletons[$id] = $service;

        return $service;
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}

// Usage
$baseContainer = createForkableContainer();

// Each sub-runtime gets a fork
$subRuntime1 = new StandardRuntime($subRegion1, new RuntimeConfig(
    container: $baseContainer->fork(),
));

$subRuntime2 = new StandardRuntime($subRegion2, new RuntimeConfig(
    container: $baseContainer->fork(),
));

// Sub-runtimes share definitions but not instances
```

### Scoped Services

```php
class ScopedContainer implements ContainerInterface
{
    private array $scopes = [];

    public function enterScope(string $name): void
    {
        $this->scopes[] = $name;
    }

    public function exitScope(): void
    {
        array_pop($this->scopes);
    }

    public function get(string $id): mixed
    {
        $scope = end($this->scopes) ?: 'global';
        $key = $scope . ':' . $id;

        // Service lookup logic with scope isolation
        // ...
    }
}

// Usage
$container = new ScopedContainer();

$runtime = new StandardRuntime($region, new RuntimeConfig(
    container: $container,
    onIteration: function($region, $trigger, $iteration) use ($container) {
        // Enter scope for this iteration
        $container->enterScope("iteration-$iteration");

        // Cleanup after state processing
        register_shutdown_function(fn() => $container->exitScope());
    },
));
```

---

## Advanced Patterns

### Tee Pipeline (Fan-Out)

```php
class TeePipeline
{
    private Runtime $source;
    private array $outputs = [];

    public function __construct(Runtime $source)
    {
        $this->source = $source;
    }

    public function tee(Runtime $output): self
    {
        $this->outputs[] = $output;
        return $this;
    }

    public function run(?callable $onComplete = null): void
    {
        // Wire source to all outputs
        $this->source->getRegion()->on(function($event) {
            foreach ($this->outputs as $output) {
                $output->getRegion()->trigger($event);
            }
        });

        // Run source
        $this->source->run($onComplete);

        // Run all outputs
        foreach ($this->outputs as $output) {
            $output->run();
        }
    }
}

// Usage
(new TeePipeline($parser))
    ->tee($processor1) // Send to multiple processors
    ->tee($processor2)
    ->tee($logger)
    ->run();
```

### Merge Pipeline (Fan-In)

```php
class MergePipeline
{
    private array $sources = [];
    private Runtime $sink;

    public function addSource(Runtime $source): self
    {
        $this->sources[] = $source;
        return $this;
    }

    public function setSink(Runtime $sink): self
    {
        $this->sink = $sink;
        return $this;
    }

    public function run(?callable $onComplete = null): void
    {
        // Wire all sources to sink
        foreach ($this->sources as $source) {
            $source->getRegion()->on(function($event) {
                $this->sink->getRegion()->trigger($event);
            });
        }

        // Run all sources
        foreach ($this->sources as $source) {
            $source->run();
        }

        // Run sink
        $this->sink->run($onComplete);
    }
}

// Usage
(new MergePipeline())
    ->addSource($source1)
    ->addSource($source2)
    ->addSource($source3)
    ->setSink($aggregator)
    ->run(onComplete: fn($r) => echo "All merged!");
```

### Circuit Breaker Pattern

```php
class CircuitBreakerRuntime implements Runtime
{
    private int $failures = 0;
    private bool $open = false;

    public function __construct(
        private Runtime $wrapped,
        private int $threshold = 5,
        private float $timeout = 60.0,
    ) {}

    public function run(?callable $onComplete = null): void
    {
        if ($this->open) {
            throw new \RuntimeException('Circuit breaker is open');
        }

        try {
            $this->wrapped->run(onComplete: function($result) use ($onComplete) {
                $this->failures = 0; // Reset on success
                $onComplete && $onComplete($result);
            });
        } catch (\Throwable $e) {
            $this->failures++;

            if ($this->failures >= $this->threshold) {
                $this->open = true;
                // Schedule reset after timeout
                // (implementation depends on async framework)
            }

            throw $e;
        }
    }

    // Implement other Runtime methods by delegating to $wrapped
}

// Usage
$runtime = new CircuitBreakerRuntime(
    wrapped: new StandardRuntime($region),
    threshold: 3,
    timeout: 30.0,
);
```

This covers the major usage patterns for the Runtime abstraction!