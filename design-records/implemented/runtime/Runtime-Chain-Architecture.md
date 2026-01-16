# Runtime Chain-Based Piping Architecture

**Companion to**: [Runtime.md](Runtime.md)
**Status**: Draft
**Date**: 2025-12-13

## Overview

This document details how Runtime piping leverages the existing Chain/ChainMail middleware infrastructure. By treating Runtimes as chain links, we achieve:

- **Composition** - Pipe multiple Runtimes declaratively
- **Filtering** - Apply middleware to events between Runtimes
- **Transformation** - Modify events as they flow through the pipeline
- **Reusability** - Build pipeline templates with ChainMail

## Chain Infrastructure Review

### Current Chain Pattern

From `src/Middleware/Chain.php`:

```php
/**
 * @template C (Context type)
 * @template R (Result type)
 */
class Chain {
    /**
     * Link middleware into the chain
     *
     * @param callable(C $context, callable $next, callable $first): R
     * @return callable Deregister function
     */
    public function link(callable $callback): callable;

    /**
     * Execute the chain with context
     *
     * @param C $context
     * @return R
     */
    public function call(mixed $context): mixed;
}
```

### Key Properties

1. **Generic over context and result** - Can represent any pipeline
2. **Middleware signature** - `fn($context, $next, $first): $result`
   - `$context` - Input data flowing through chain
   - `$next` - Call next middleware
   - `$first` - Restart from beginning
3. **Deregistration** - `link()` returns cleanup function
4. **Restart tracking** - Prevents infinite loops

## Runtime Pipeline Design

### Core Abstraction

```php
namespace Noem\State\Chains;

use Noem\State\Middleware\Chain;
use Noem\State\Runtime;

/**
 * Chain for composing Runtime instances
 *
 * Context: Event/trigger object flowing through pipeline
 * Result: Processed event (potentially transformed)
 *
 * @template-extends Chain<object, object>
 */
class RuntimePipeline extends Chain
{
    /**
     * @var list<Runtime> Runtimes in the pipeline
     */
    private array $runtimes = [];

    public function __construct()
    {
        parent::__construct(
            // Provider: Pass-through (return context unchanged)
            provider: fn(object $context): object => $context
        );
    }

    /**
     * Add a Runtime to the pipeline
     *
     * Automatically creates middleware that:
     * 1. Triggers the runtime with the context
     * 2. Collects events emitted by the runtime
     * 3. Passes events to next middleware
     *
     * @param Runtime $runtime
     * @param callable|null $filter Optional filter: fn(object $event): bool
     * @return $this
     */
    public function pipe(Runtime $runtime, ?callable $filter = null): self
    {
        $this->runtimes[] = $runtime;

        $this->link(function(object $context, callable $next) use ($runtime, $filter) {
            // Trigger the runtime with this context
            $runtime->getRegion()->trigger($context);

            // Collect events from runtime
            foreach ($runtime->events() as $event) {
                // Apply filter if provided
                if ($filter && !$filter($event)) {
                    continue;
                }

                // Pass event to next middleware
                $next($event);
            }

            // Return the original context (or last event)
            return $context;
        });

        return $this;
    }

    /**
     * Add a transformation middleware
     *
     * @param callable(object $event): object $transformer
     * @return $this
     */
    public function transform(callable $transformer): self
    {
        $this->link(function(object $context, callable $next) use ($transformer) {
            $transformed = $transformer($context);
            return $next($transformed);
        });

        return $this;
    }

    /**
     * Add a filter middleware
     *
     * Events not passing filter are dropped (not passed to next)
     *
     * @param callable(object $event): bool $predicate
     * @return $this
     */
    public function filter(callable $predicate): self
    {
        $this->link(function(object $context, callable $next) use ($predicate) {
            if ($predicate($context)) {
                return $next($context);
            }
            return $context; // Drop event
        });

        return $this;
    }

    /**
     * Execute the pipeline with an initial trigger
     *
     * @param object $trigger Initial event to inject
     * @param callable|null $onComplete Callback when all runtimes complete
     * @return void
     */
    public function run(object $trigger, ?callable $onComplete = null): void
    {
        // Call the chain with the initial trigger
        $this->call($trigger);

        // Run all runtimes to completion
        foreach ($this->runtimes as $runtime) {
            $runtime->run($onComplete);
        }
    }

    /**
     * Get all runtimes in the pipeline
     *
     * @return list<Runtime>
     */
    public function getRuntimes(): array
    {
        return $this->runtimes;
    }
}
```

## Usage Patterns

### Basic Pipeline

```php
use Noem\State\Chains\RuntimePipeline;
use Noem\State\StandardRuntime;

// Create runtimes
$parser = new StandardRuntime($parserRegion);
$validator = new StandardRuntime($validatorRegion);
$processor = new StandardRuntime($processorRegion);

// Compose pipeline
$pipeline = new RuntimePipeline();
$pipeline
    ->pipe($parser)
    ->pipe($validator)
    ->pipe($processor);

// Execute with initial trigger
$pipeline->run(
    trigger: new ParseFile('document.json'),
    onComplete: fn($result) => echo "Pipeline complete!\n"
);
```

### With Filtering

```php
$pipeline = new RuntimePipeline();
$pipeline
    ->pipe($parser)
    // Only pass Chunk events to validator
    ->pipe($validator, filter: fn($e) => $e instanceof Chunk)
    // Only pass ValidChunk events to processor
    ->pipe($processor, filter: fn($e) => $e instanceof ValidChunk);

$pipeline->run(new ParseFile('data.csv'));
```

### With Transformation

```php
$pipeline = new RuntimePipeline();
$pipeline
    ->pipe($parser)
    // Transform raw chunks into enriched chunks
    ->transform(fn($event) => match(true) {
        $event instanceof RawChunk => new EnrichedChunk(
            data: $event->data,
            metadata: extractMetadata($event->data),
        ),
        default => $event,
    })
    ->pipe($processor);

$pipeline->run(new StartParsing());
```

### Branching with Multiple Pipes

```php
// Create different pipelines for different event types
$jsonPipeline = (new RuntimePipeline())
    ->filter(fn($e) => $e instanceof JsonEvent)
    ->pipe($jsonProcessor);

$xmlPipeline = (new RuntimePipeline())
    ->filter(fn($e) => $e instanceof XmlEvent)
    ->pipe($xmlProcessor);

// Main pipeline branches to both
$mainPipeline = new RuntimePipeline();
$mainPipeline
    ->pipe($parser)
    ->link(function(object $context, callable $next) use ($jsonPipeline, $xmlPipeline) {
        // Route to appropriate sub-pipeline
        if ($context instanceof JsonEvent) {
            $jsonPipeline->call($context);
        } elseif ($context instanceof XmlEvent) {
            $xmlPipeline->call($context);
        }
        return $next($context);
    });
```

### Tee (Fan-Out) Pattern

```php
class TeePipeline extends RuntimePipeline
{
    private array $branches = [];

    public function tee(RuntimePipeline $branch): self
    {
        $this->branches[] = $branch;

        $this->link(function(object $context, callable $next) use ($branch) {
            // Send event to branch (don't wait for completion)
            $branch->call($context);

            // Continue main pipeline
            return $next($context);
        });

        return $this;
    }

    public function run(object $trigger, ?callable $onComplete = null): void
    {
        parent::run($trigger, $onComplete);

        // Also run all branches
        foreach ($this->branches as $branch) {
            $branch->run($trigger);
        }
    }
}

// Usage
$main = new TeePipeline();
$main
    ->pipe($parser)
    ->tee(
        // Branch 1: Log events
        (new RuntimePipeline())->pipe($logger)
    )
    ->tee(
        // Branch 2: Metrics
        (new RuntimePipeline())->pipe($metrics)
    )
    ->pipe($processor); // Main path continues

$main->run(new StartProcessing());
```

### Merge (Fan-In) Pattern

```php
class MergePipeline extends RuntimePipeline
{
    private array $sources = [];

    public function addSource(Runtime $source): self
    {
        $this->sources[] = $source;

        // Wire source events into this pipeline
        $source->getRegion()->on(function($event) {
            $this->call($event);
        });

        return $this;
    }

    public function run(?callable $onComplete = null): void
    {
        // Run all sources
        foreach ($this->sources as $source) {
            $source->run();
        }

        // Run this pipeline's runtimes
        foreach ($this->getRuntimes() as $runtime) {
            $runtime->run($onComplete);
        }
    }
}

// Usage
$merge = new MergePipeline();
$merge
    ->addSource($source1)
    ->addSource($source2)
    ->addSource($source3)
    ->pipe($aggregator)
    ->pipe($processor);

$merge->run(onComplete: fn($r) => echo "All merged!\n");
```

## Advanced Middleware

### Rate Limiting

```php
class RateLimitedPipeline extends RuntimePipeline
{
    public function rateLimit(int $maxPerSecond): self
    {
        $lastTime = 0.0;
        $minInterval = 1.0 / $maxPerSecond;

        $this->link(function(object $context, callable $next) use (&$lastTime, $minInterval) {
            $now = microtime(true);
            $elapsed = $now - $lastTime;

            if ($elapsed < $minInterval) {
                usleep((int)(($minInterval - $elapsed) * 1_000_000));
            }

            $lastTime = microtime(true);
            return $next($context);
        });

        return $this;
    }
}

// Usage
(new RateLimitedPipeline())
    ->pipe($parser)
    ->rateLimit(100) // Max 100 events/second
    ->pipe($apiClient);
```

### Batching

```php
class BatchingPipeline extends RuntimePipeline
{
    public function batch(int $size, int $timeoutMs = 1000): self
    {
        $buffer = [];
        $lastFlush = microtime(true);

        $this->link(function(object $context, callable $next) use (&$buffer, &$lastFlush, $size, $timeoutMs) {
            $buffer[] = $context;
            $now = microtime(true);
            $elapsed = ($now - $lastFlush) * 1000;

            // Flush if batch is full or timeout reached
            if (count($buffer) >= $size || $elapsed >= $timeoutMs) {
                $batch = new EventBatch($buffer);
                $buffer = [];
                $lastFlush = $now;
                return $next($batch);
            }

            return $context; // Hold event in buffer
        });

        return $this;
    }
}

// Usage
(new BatchingPipeline())
    ->pipe($parser)
    ->batch(size: 100, timeoutMs: 5000)
    ->pipe($batchProcessor); // Receives EventBatch objects
```

### Retry Logic

```php
class RetryingPipeline extends RuntimePipeline
{
    public function retry(int $maxAttempts = 3, int $delayMs = 1000): self
    {
        $this->link(function(object $context, callable $next) use ($maxAttempts, $delayMs) {
            $attempts = 0;

            while ($attempts < $maxAttempts) {
                try {
                    return $next($context);
                } catch (\Throwable $e) {
                    $attempts++;

                    if ($attempts >= $maxAttempts) {
                        throw $e;
                    }

                    usleep($delayMs * 1000);
                }
            }

            throw new \RuntimeException('Max retries exceeded');
        });

        return $this;
    }
}

// Usage
(new RetryingPipeline())
    ->pipe($parser)
    ->retry(maxAttempts: 3, delayMs: 2000)
    ->pipe($unreliableService);
```

## ChainMail Integration

ChainMail allows extracting pipeline templates as reusable configurations.

### Pipeline Templates

```php
use Noem\State\Middleware\ChainMail;

class PipelineTemplates
{
    public static function createValidationPipeline(): ChainMail
    {
        $mail = new ChainMail();

        // Standard validation middleware
        $mail->add('filter_nulls', function(object $context, callable $next) {
            if ($context instanceof NullEvent) {
                return $context; // Drop nulls
            }
            return $next($context);
        });

        $mail->add('validate_schema', function(object $context, callable $next) {
            if (!validateSchema($context)) {
                throw new ValidationException('Schema validation failed');
            }
            return $next($context);
        });

        $mail->add('enrich_metadata', function(object $context, callable $next) {
            if (method_exists($context, 'setMetadata')) {
                $context->setMetadata(extractMetadata($context));
            }
            return $next($context);
        });

        return $mail;
    }

    public static function createLoggingPipeline(): ChainMail
    {
        $mail = new ChainMail();

        $mail->add('log_entry', function(object $context, callable $next) {
            logger()->debug('Pipeline event', [
                'type' => get_class($context),
                'timestamp' => microtime(true),
            ]);
            return $next($context);
        });

        $mail->add('log_exit', function(object $context, callable $next) {
            $result = $next($context);
            logger()->debug('Pipeline event completed', [
                'type' => get_class($context),
            ]);
            return $result;
        });

        return $mail;
    }
}

// Usage
$pipeline = new RuntimePipeline();

// Apply validation template
$validationMail = PipelineTemplates::createValidationPipeline();
foreach ($validationMail->getMiddlewares() as $middleware) {
    $pipeline->link($middleware);
}

// Add runtime
$pipeline->pipe($processor);

// Apply logging template
$loggingMail = PipelineTemplates::createLoggingPipeline();
foreach ($loggingMail->getMiddlewares() as $middleware) {
    $pipeline->link($middleware);
}
```

### Composable Pipeline Builder

```php
class PipelineBuilder
{
    private RuntimePipeline $pipeline;
    private array $templates = [];

    public function __construct()
    {
        $this->pipeline = new RuntimePipeline();
    }

    public function applyTemplate(ChainMail $template): self
    {
        foreach ($template->getMiddlewares() as $middleware) {
            $this->pipeline->link($middleware);
        }
        return $this;
    }

    public function pipe(Runtime $runtime, ?callable $filter = null): self
    {
        $this->pipeline->pipe($runtime, $filter);
        return $this;
    }

    public function build(): RuntimePipeline
    {
        return $this->pipeline;
    }
}

// Usage
$pipeline = (new PipelineBuilder())
    ->applyTemplate(PipelineTemplates::createLoggingPipeline())
    ->applyTemplate(PipelineTemplates::createValidationPipeline())
    ->pipe($parser)
    ->pipe($processor)
    ->build();
```

## Container Sharing Through Pipelines

### Shared Container Strategy

```php
$container = createContainer([
    'logger' => new Logger(),
    'cache' => new Cache(),
]);

$pipeline = new RuntimePipeline();
$pipeline
    ->pipe(new StandardRuntime($region1, new RuntimeConfig(
        container: $container,
        shareContainer: true,
    )))
    ->pipe(new StandardRuntime($region2, new RuntimeConfig(
        container: $container,
        shareContainer: true,
    )));

// Both runtimes share the same container
```

### Container Middleware

```php
class ContainerAwarePipeline extends RuntimePipeline
{
    public function __construct(
        private ContainerInterface $container,
    ) {
        parent::__construct();

        // Inject container into all events
        $this->link(function(object $context, callable $next) {
            if (method_exists($context, 'setContainer')) {
                $context->setContainer($this->container);
            }
            return $next($context);
        });
    }
}

// Usage
$pipeline = new ContainerAwarePipeline($container);
$pipeline
    ->pipe($runtime1)
    ->pipe($runtime2);
// All events get container access
```

## Testing Pipelines

### Mock Runtime Testing

```php
class MockRuntime implements Runtime
{
    private array $receivedEvents = [];
    private array $emittedEvents;

    public function __construct(array $emittedEvents = [])
    {
        $this->emittedEvents = $emittedEvents;
    }

    public function getRegion(): Region
    {
        return new class extends Region {
            public function trigger(object $payload, bool $enqueue = false): object
            {
                $this->receivedEvents[] = $payload;
                return $payload;
            }
        };
    }

    public function events(): \Generator
    {
        foreach ($this->emittedEvents as $event) {
            yield $event;
        }
    }

    public function getReceivedEvents(): array
    {
        return $this->receivedEvents;
    }

    // ... implement other Runtime methods ...
}

// Test
$mock1 = new MockRuntime([new Event1(), new Event2()]);
$mock2 = new MockRuntime();

$pipeline = (new RuntimePipeline())
    ->pipe($mock1)
    ->pipe($mock2);

$pipeline->run(new InitialEvent());

// Assert mock2 received events from mock1
assert(count($mock2->getReceivedEvents()) === 2);
```

### Pipeline Assertions

```php
class PipelineAssertion
{
    private array $events = [];

    public function __construct(private RuntimePipeline $pipeline)
    {
        // Capture all events
        $this->pipeline->link(function(object $context, callable $next) {
            $this->events[] = $context;
            return $next($context);
        });
    }

    public function assertEventCount(int $expected): void
    {
        assert(count($this->events) === $expected);
    }

    public function assertEventTypes(array $expectedTypes): void
    {
        $actualTypes = array_map('get_class', $this->events);
        assert($actualTypes === $expectedTypes);
    }

    public function assertEventPassed(callable $predicate): void
    {
        foreach ($this->events as $event) {
            if ($predicate($event)) {
                return; // Found matching event
            }
        }
        throw new AssertionError('No event matched predicate');
    }
}

// Usage in tests
$assertion = new PipelineAssertion($pipeline);
$pipeline->run(new InitialEvent());

$assertion->assertEventCount(5);
$assertion->assertEventTypes([Event1::class, Event2::class, Event3::class]);
$assertion->assertEventPassed(fn($e) => $e instanceof CriticalEvent);
```

## Performance Considerations

### Event Buffer Management

For long-running pipelines, events should be streamed rather than buffered:

```php
class StreamingRuntime implements Runtime
{
    private \SplQueue $eventQueue;

    public function __construct(private Region $region)
    {
        $this->eventQueue = new \SplQueue();

        // Subscribe to notifications, add to queue
        $this->region->on(function($event) {
            $this->eventQueue->enqueue($event);
        });
    }

    public function events(): \Generator
    {
        while (!$this->eventQueue->isEmpty()) {
            yield $this->eventQueue->dequeue();
        }
    }

    // ... other methods ...
}
```

### Memory-Efficient Pipeline

```php
$pipeline = new RuntimePipeline();
$pipeline
    ->pipe(new StreamingRuntime($parser))
    ->filter(fn($e) => $e instanceof Chunk) // Drop non-chunks early
    ->transform(fn($e) => processChunk($e)) // Transform immediately
    ->pipe(new StreamingRuntime($sink)); // Stream to sink

// Events are processed and discarded, not held in memory
```

## Conclusion

The Chain-based Runtime piping architecture provides:

- **Declarative composition** - Build pipelines fluently
- **Middleware reusability** - Extract templates with ChainMail
- **Flexible routing** - Filter, transform, branch, merge
- **Performance control** - Rate limiting, batching, streaming
- **Testability** - Mock runtimes, pipeline assertions

This design leverages existing Chain infrastructure, ensuring consistency with the rest of the state machine architecture.