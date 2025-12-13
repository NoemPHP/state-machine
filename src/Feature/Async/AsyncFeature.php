<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Chains;
use Noem\State\Chains\Params;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\Loader\LoaderChains;
use Noem\State\Feature\Async\AsyncChains\Params as AsyncParams;
use Noem\State\Feature\Async\Config\AsyncConfig as ConfigAsyncConfig;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Middleware\ChainException;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;
use Noem\State\Region;
use Noem\State\RegionBuilder;

class AsyncFeature implements Feature
{
    private \SplObjectStorage $coroutinesByRegion;

    /**
     * A map to connect Tasks and their originating callback
     * (the one that returns the Generator backing the task)
     *
     * @var \WeakMap<callable,Task>
     */
    private \WeakMap $callbackTaskMap;

    /**
     * Maps callbacks directly to their AsyncConfig, bypassing the need for callback identity matching.
     * This is populated when callbacks are registered and works regardless of binding.
     *
     * @var \SplObjectStorage<callable,AsyncConfig>
     */
    private \SplObjectStorage $callbackConfigMap;

    public function __construct()
    {
        $this->coroutinesByRegion = new \SplObjectStorage();
        $this->callbackTaskMap = new \WeakMap();
        $this->callbackConfigMap = new \SplObjectStorage();
    }

    /**
     * @throws ChainException
     */
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(
            fn(): AsyncChains\Enqueue => new AsyncChains\Enqueue($this->coroutinesByRegion),
            fn(): Resolvers => new Resolvers(),
            fn(): AsyncCallbackType => AsyncCallbackType::get()
        );
        $chainMail
            ->use($this->trackBoundCallbacks(...))
            ->use($this->deferTicksUntilActionComplete(...))
            ->use($this->enqueueCoroutines(...))
            ->use($this->extendBuilderSchema(...))
            ->use($this->processBuilderConfig(...))
            ->use($this->transformAsyncCallbacksInArray(...))
            ->use($this->triggerResolversOnMetadataAccess(...))
            ->use($this->setupAsyncContextMethods(...));
    }

    /**
     * Populates the callback -> config map by intercepting PrepareInvokable.
     * When we see the original callback, we look it up in the registry and store the config.
     * Then we also store the config for the bound version.
     */
    private function trackBoundCallbacks(
        ?Chains\PrepareInvokable $prepareInvokable,
        ?CallbackRegistry $registry,
    ): void {
        if (!$prepareInvokable || !$registry) {
            return;
        }

        $prepareInvokable->link(function (Params\Callback $context, callable $next) use ($registry) {
            $original = $context->handler;

            // If we haven't seen this callback before, look it up in the registry
            if (!$this->callbackConfigMap->contains($original)) {
                $records = $registry->query(
                    region: $context->region,
                    type: AsyncCallbackType::get(),
                    event: $context->event,
                    state: $context->state
                );

                foreach ($records as $record) {
                    if ($record->callback === $original && $record->metadata instanceof AsyncConfig) {
                        // Store the mapping from original callback to config
                        $this->callbackConfigMap->attach($original, $record->metadata);
                        break;
                    }
                }
            }

            // Let other middleware prepare the callback
            $prepared = $next($context);

            // If the callback was bound, also map the bound version to the same config
            if ($prepared !== $original && $this->callbackConfigMap->contains($original)) {
                $config = $this->callbackConfigMap[$original];
                if (!$this->callbackConfigMap->contains($prepared)) {
                    $this->callbackConfigMap->attach($prepared, $config);
                }
            }

            return $prepared;
        }, prepend: true);
    }

    /**
     * Check if we should create a new generator for debounce/throttle
     *
     * Debounce: After period elapses, use existing generator (latest from debounce period)
     * Throttle: After period elapses, create new generator (current trigger executes)
     */
    private function shouldCreateNewGenerator(
        Task $task,
        AsyncConfig $config,
        CoroutineScheduler $scheduler
    ): bool {
        $now = microtime(true);

        // Check debounce timing
        if ($config->debounce !== null) {
            $enqueueTime = $scheduler->getDebounceTime($task);
            if ($enqueueTime !== null) {
                $elapsed = $now - $enqueueTime;
                if ($elapsed >= $config->debounce) {
                    // Debounce period elapsed - use existing generator with latest payload from period
                    return false;
                }
                // Within debounce period - create new generator to capture latest payload
                return true;
            }
        }

        // Check throttle timing
        if ($config->throttle !== null) {
            $lastExecution = $scheduler->getThrottleTime($task);
            if ($lastExecution !== null) {
                $elapsed = $now - $lastExecution;
                if ($elapsed < $config->throttle) {
                    // Within throttle period - create new generator to capture latest payload
                    return true;
                }
                // Throttle period elapsed - create new generator for current trigger to execute
                return true;
            }
        }

        // Default: create new generator
        return true;
    }

    /**
     * Defers ticking until all action callbacks have been processed.
     * This ensures that all async tasks progress exactly once per trigger,
     * maintaining fairness when multiple async callbacks are registered.
     */
    private function deferTicksUntilActionComplete(
        ?Chains\DispatchAction $dispatchAction,
    ): void {
        if (!$dispatchAction) {
            return;
        }

        $dispatchAction->link(function (Params\Action $context, callable $next) {
            $region = $context->region;

            // Ensure scheduler exists
            $scheduler = $this->getCoroutineSchedulerForRegion($this->coroutinesByRegion, $region);

            // Clean up finished tasks BEFORE processing callbacks
            // This ensures callbacks that finished in the previous trigger are removed from the map
            // EXCEPT for callbacks with throttle/debounce which require singleton behavior
            // CRITICAL: For throttle/debounce, NEVER call isFinished() as it triggers generator execution
            // Collect finished tasks first to avoid modification during iteration
            $finishedCallbacks = [];
            foreach ($this->callbackTaskMap as $callback => $task) {
                // Check if this callback has AsyncConfig requiring singleton behavior
                $asyncConfig = null;
                if ($this->callbackConfigMap->contains($callback)) {
                    $asyncConfig = $this->callbackConfigMap[$callback];
                }

                $requiresSingleton = $asyncConfig !== null && (
                    $asyncConfig->singleton ||
                    $asyncConfig->throttle !== null ||
                    $asyncConfig->debounce !== null
                );

                // For singleton/throttle/debounce, never check if finished - scheduler handles lifecycle
                if ($requiresSingleton) {
                    continue;
                }

                // Only check isFinished() for non-singleton tasks
                if ($task->isFinished()) {
                    $finishedCallbacks[] = $callback;
                }
            }
            // Now remove them
            foreach ($finishedCallbacks as $callback) {
                unset($this->callbackTaskMap[$callback]);
            }

            // Process all callbacks
            $result = $next($context);

            // Tick once after all callbacks have been processed
            $scheduler->tick();

            return $result;
        });
    }

    private function enqueueCoroutines(
        Chains\InvokeCallback $invokeCallback,
        AsyncChains\Enqueue $enqueue,
        ?CallbackRegistry $registry = null,
    ): void {
        /**
         * Modify the invoke callback to handle asynchronous callbacks
         */
        $invokeCallback->link(
            function (
                Params\Callback $context,
                callable $next,
                callable $first
            ) use (
                $enqueue,
                $registry
            ): mixed {
                if (!$this->coroutinesByRegion->contains($context->region)) {
                    $this->coroutinesByRegion->attach($context->region, new CoroutineScheduler());
                }
                $coroutines = $this->coroutinesByRegion[$context->region];
                assert($coroutines instanceof CoroutineScheduler);

                // Check if callback has AsyncConfig in our map
                $asyncConfig = null;
                if ($this->callbackConfigMap->contains($context->handler)) {
                    $asyncConfig = $this->callbackConfigMap[$context->handler];
                    assert($asyncConfig instanceof AsyncConfig);
                }

                /**
                 * Check if callback requires singleton behavior (explicit singleton, throttle, or debounce).
                 * Throttle/debounce semantically require singleton to work correctly:
                 * - Throttle: Rate limiting requires returning the same task instance
                 * - Debounce: Timer reset requires operating on the same task instance
                 */
                $requiresSingleton = $asyncConfig !== null && (
                    $asyncConfig->singleton ||
                    $asyncConfig->throttle !== null ||
                    $asyncConfig->debounce !== null
                );

                /**
                 * If we already track a Task, check if it's still running.
                 * For finished tasks with singleton/throttle/debounce, we still create a new generator
                 * and let the scheduler decide whether to accept it based on timing.
                 * Creating a Generator doesn't execute code (that only happens on first current() call).
                 */
                if ($this->callbackTaskMap->offsetExists($context->handler)) {
                    $task = $this->callbackTaskMap[$context->handler];

                    // For non-singleton tasks, check if finished
                    if (!$requiresSingleton) {
                        if (!$task->isFinished()) {
                            // Task still running - async callbacks don't return synchronous values
                            return null;
                        }
                        // Task finished, remove from map and create new
                        unset($this->callbackTaskMap[$context->handler]);
                        // Fall through to create new generator
                    } else {
                        // For singleton/throttle/debounce:
                        // Check if timing period has elapsed - if so, use existing generator
                        // Otherwise create new generator to capture latest payload
                        $shouldCreateNew = $this->shouldCreateNewGenerator($task, $asyncConfig, $coroutines);
                        if (!$shouldCreateNew) {
                            // Timing period elapsed, using existing generator - async callbacks don't return sync values
                            return null;
                        }
                        // Fall through to create new generator
                    }
                }

                /**
                 * Invoke callback to get result (sync value or new generator).
                 * For finished throttle/debounce tasks, scheduler will check timing and either:
                 * - Accept new generator if period elapsed
                 * - Return existing task if still throttled/debounced (new generator discarded)
                 */
                $result = $next($context);

                // If callback is explicitly registered as async, it must return a Generator
                if ($asyncConfig !== null) {
                    if (!$result instanceof \Generator) {
                        throw new \RuntimeException(
                            "Callback registered with AsyncCallbackType must return a Generator"
                        );
                    }

                    // Enqueue with AsyncConfig from metadata
                    $task = $coroutines->enqueue($result, $asyncConfig, $context->handler);
                    $this->callbackTaskMap[$context->handler] = $task;

                    // Async callbacks don't return synchronous values
                    return null;
                }

                // Auto-detect generators even without explicit AsyncConfig
                if ($result instanceof \Generator) {
                    // Use default AsyncConfig (Priority::NORMAL, no special behavior)
                    $defaultConfig = new AsyncConfig();
                    $task = $coroutines->enqueue($result, $defaultConfig, $context->handler);
                    $this->callbackTaskMap[$context->handler] = $task;

                    // Async callbacks don't return synchronous values
                    return null;
                }

                // Not async - execute normally (sync callback)
                return $result;
            }
        );
    }

    /**
     * If the RegionLoader is used, extend its schema so that it accepts:
     * 1. Async configuration for all callbacks (actions, transitions, onEnter, onExit)
     * 2. A list of resolvers with async configuration support
     *
     * @param LoaderChains\Schema|null $schema
     *
     * @return void
     * @see RegionLoader
     */
    private function extendBuilderSchema(
        ?LoaderChains\Schema $schema,
    ): void {
        $schema?->link(function (SchemaContext $context, callable $next) {
            // Define async configuration schema
            $asyncConfigSchema = Expect::structure([
                'enabled' => Expect::bool(true),
                'priority' => Expect::anyOf('low', 'normal', 'high'),
                'singleton' => Expect::bool(false),
                'timeout' => Expect::float(),
                'debounce' => Expect::float(),
                'throttle' => Expect::float(),
            ])->skipDefaults();

            // Extend the existing action schema to add optional async field
            $context->action = $context->action->extend([
                'async' => $asyncConfigSchema,
            ]);

            // Extend transition schema to add optional async field to guard
            $transitionWithAsync = Expect::structure([
                'target' => Expect::string()->required(),
                'guard' => Expect::anyOf(
                    $context->callback,
                    Expect::structure([
                        'run' => $context->callback,
                        'async' => $asyncConfigSchema,
                    ])
                ),
            ]);

            // Update state schema to use extended action and transition schemas
            $context->state = $context->state->extend([
                'action' => Expect::listOf($context->action),  // Keep 'action' singular to match ProcessArray
                'onEnter' => Expect::listOf($context->action),
                'onExit' => Expect::listOf($context->action),
                'transitions' => Expect::listOf($transitionWithAsync),
            ]);

            // Update context schema to support resolvers with async config
            $contextSchema = $context->getCustomSchema('context');
            if ($contextSchema) {
                assert($contextSchema instanceof Structure);
                $contextSchema = $contextSchema->extend([
                    'resolvers' => Expect::listOf(
                        Expect::structure([
                            'name' => Expect::string()->required(),
                            'run' => $context->callback,  // Use shared schema directly
                            'async' => $asyncConfigSchema,
                        ])
                    ),
                ]);
                $context->addCustomSchema('context', $contextSchema);

                /**
                 * Update the reference on the region schema since we just produced a new object
                 */
                $context->region = $context->region->extend([
                    'context' => $contextSchema,
                ]);
            }

            // Update region schema to use extended state schema
            $context->region = $context->region->extend([
                'states' => Expect::listOf($context->state),
            ]);

            return $next($context);
        });
    }

    private function processBuilderConfig(
        Chains\EnhanceRegionBuilder $enhanceRegionBuilder,
        Resolvers $resolvers,
    ): void {
        $enhanceRegionBuilder->link(function (Params\BuildParams $context, callable $next) use ($resolvers) {
            $builder = $next($context);

            $asyncConfig = $context->config(ConfigAsyncConfig::class);
            if (!$asyncConfig->hasResolvers()) {
                return $builder;
            }

            assert($builder instanceof RegionBuilder);
            foreach ($asyncConfig->resolvers() as $resolver) {
                // Convert async configuration from YAML to AsyncConfig object
                $asyncConfigObj = null;
                if (isset($resolver['async'])) {
                    $asyncData = $resolver['async'];

                    // Convert priority string to Priority enum
                    $priority = Priority::NORMAL;
                    if (isset($asyncData['priority'])) {
                        $priority = match ($asyncData['priority']) {
                            'low' => Priority::LOW,
                            'normal' => Priority::NORMAL,
                            'high' => Priority::HIGH,
                            default => Priority::NORMAL,
                        };
                    }

                    $asyncConfigObj = new AsyncConfig(
                        priority: $priority,
                        singleton: $asyncData['singleton'] ?? false,
                        timeout: $asyncData['timeout'] ?? null,
                        debounce: $asyncData['debounce'] ?? null,
                        throttle: $asyncData['throttle'] ?? null,
                    );
                }

                $builder->addBuildStep(
                    new AddResolver($resolver['name'], $resolver['run'], $asyncConfigObj)
                );
            }

            return $builder;
        });
    }

    /**
     * Transform array to extract async callbacks before ProcessArray sees them.
     * Hooks into TransformArray chain to pre-process async callback configuration.
     */
    private function transformAsyncCallbacksInArray(
        ?LoaderChains\TransformArray $transformArray,
        ?Chains\EnhanceRegionBuilder $enhanceRegionBuilder = null,
    ): void {
        if (!$transformArray || !$enhanceRegionBuilder) {
            return;
        }

        // Storage for async callbacks extracted from the array
        $asyncCallbacks = [];

        // Transform array to extract async callbacks
        $transformArray->link(function (array $data, callable $next) use (&$asyncCallbacks) {
            // Store async callbacks and remove async config from array
            if (isset($data['states'])) {
                foreach ($data['states'] as &$state) {
                    // Process action callbacks
                    if (isset($state['action'])) {
                        $state['action'] = $this->extractAsyncCallbacks(
                            $state['action'],
                            $asyncCallbacks,
                            $state['name'],
                            'action'
                        );
                    }

                    // Process onEnter callbacks
                    if (isset($state['onEnter'])) {
                        $state['onEnter'] = $this->extractAsyncCallbacks(
                            $state['onEnter'],
                            $asyncCallbacks,
                            $state['name'],
                            'onEnter'
                        );
                    }

                    // Process onExit callbacks
                    if (isset($state['onExit'])) {
                        $state['onExit'] = $this->extractAsyncCallbacks(
                            $state['onExit'],
                            $asyncCallbacks,
                            $state['name'],
                            'onExit'
                        );
                    }

                    // Process transition guards
                    if (isset($state['transitions'])) {
                        foreach ($state['transitions'] as &$transition) {
                            if (isset($transition['guard']) && is_array($transition['guard']) && isset($transition['guard']['async'])) {
                                $asyncCallbacks[] = [
                                    'callback' => $transition['guard']['run'],
                                    'async' => $transition['guard']['async'],
                                    'state' => $state['name'],
                                    'type' => 'guard',
                                    'target' => $transition['target'] ?? null,
                                ];
                                // Replace guard with just the callback for ProcessArray
                                $transition['guard'] = $transition['guard']['run'];
                            }
                        }
                        unset($transition);
                    }
                }
                unset($state);
            }

            return $next($data);
        });

        // Register async callbacks as AddCallback BuildSteps after ProcessArray runs
        $enhanceRegionBuilder->link(function (Params\BuildParams $context, callable $next) use (&$asyncCallbacks) {
            $builder = $next($context);

            if (empty($asyncCallbacks)) {
                return $builder;
            }

            assert($builder instanceof RegionBuilder);
            foreach ($asyncCallbacks as $asyncCallback) {
                $this->registerAsyncCallback(
                    $builder,
                    $asyncCallback['callback'],
                    $asyncCallback['async'],
                    $asyncCallback['type'],
                    $asyncCallback['state'],
                    $asyncCallback['target'] ?? null
                );
            }

            // Clear the array for next build
            $asyncCallbacks = [];

            return $builder;
        });
    }

    /**
     * Extract async callbacks from a callback list and return the modified list.
     */
    private function extractAsyncCallbacks(array $callbacks, array &$asyncCallbacks, string $stateName, string $type): array
    {
        $result = [];

        foreach ($callbacks as $callback) {
            if (is_array($callback) && isset($callback['async'])) {
                // Check if async is disabled
                if (isset($callback['async']['enabled']) && $callback['async']['enabled'] === false) {
                    // Async disabled, treat as sync - keep in array for ProcessArray
                    $result[] = $callback;
                } else {
                    // Extract async callback
                    $asyncCallbacks[] = [
                        'callback' => $callback['run'],
                        'async' => $callback['async'],
                        'state' => $stateName,
                        'type' => $type,
                        'target' => null,
                    ];
                    // Don't add to result - ProcessArray won't see it
                }
            } else {
                // Regular callback without async config
                $result[] = $callback;
            }
        }

        return $result;
    }

    /**
     * Register a callback with AsyncCallbackType and AsyncConfig.
     */
    private function registerAsyncCallback(
        RegionBuilder $builder,
        \Closure $callback,
        array $asyncConfig,
        string $callbackType,
        string $stateName,
        ?string $target = null
    ): void {
        // Check if async is disabled - if so, skip async registration
        if (isset($asyncConfig['enabled']) && $asyncConfig['enabled'] === false) {
            return;  // Don't register as async, will fall back to default sync callback type
        }

        // Skip guards - they're not supported via AddCallback
        // TODO: Implement async guard support through a different mechanism
        if ($callbackType === 'guard') {
            return;
        }

        // Convert async configuration from YAML to AsyncConfig object
        $priority = Priority::NORMAL;
        if (isset($asyncConfig['priority'])) {
            $priority = match ($asyncConfig['priority']) {
                'low' => Priority::LOW,
                'normal' => Priority::NORMAL,
                'high' => Priority::HIGH,
                default => Priority::NORMAL,
            };
        }

        $asyncConfigObj = new AsyncConfig(
            priority: $priority,
            singleton: $asyncConfig['singleton'] ?? false,
            timeout: $asyncConfig['timeout'] ?? null,
            debounce: $asyncConfig['debounce'] ?? null,
            throttle: $asyncConfig['throttle'] ?? null,
        );

        // Map callback type to event name
        $event = $this->mapCallbackTypeToEvent($callbackType);

        // Register callback using AddCallback BuildStep
        $builder->addBuildStep(
            new \Noem\State\Callbacks\AddCallback(
                event: $event,
                state: $stateName,
                callback: $callback,
                type: AsyncCallbackType::get(),
                metadata: $asyncConfigObj
            )
        );
    }

    /**
     * Map YAML callback type to event name for AddCallback.
     */
    private function mapCallbackTypeToEvent(string $callbackType): string
    {
        return match ($callbackType) {
            'action' => 'action',
            'onEnter' => 'enter',
            'onExit' => 'exit',
            default => throw new \InvalidArgumentException("Unknown callback type: $callbackType"),
        };
    }

    private function triggerResolversOnMetadataAccess(
        Chains\Meta $meta,
        Chains\PrepareInvokable $prepareInvokable,
        Chains\InvokeCallback $invokeCallback,
        AsyncChains\Enqueue $enqueue,
        Resolvers $resolvers,
    ): void {
        /**
         * Trigger resolvers when corresponding meta data is accessed
         */
        $observers = new \SplObjectStorage();
        $meta->link(
            function (
                Params\Meta $metaParams,
                callable $next
            ) use (
                $observers,
                $prepareInvokable,
                $invokeCallback,
                $enqueue,
                $resolvers
            ): Mesh {
                $mesh = $next($metaParams);
                assert($mesh instanceof Mesh);
                /**
                 * We are only interested in the context metadata
                 */
                if (!$metaParams->type->is(ContextMetaType::class)) {
                    return $mesh;
                }
                /**
                 * Observers already set up? Then we can bail
                 * TODO Maybe this should be scoped by Mesh and not Region?
                 * A region could be part of multiple shared meshes and resolvers
                 * might yield different results based on their environment
                 */
                if ($observers->contains($metaParams->region)) {
                    return $mesh;
                }
                $ornaments = [];
                $resolvers = $resolvers->getResolversForRegion($metaParams->region);
                $scheduler = $this->getCoroutineSchedulerForRegion(
                    $this->coroutinesByRegion,
                    $metaParams->region
                );
                foreach ($resolvers as $offset => $resolver) {
                    $ornaments[] = new Ornament(
                        $mesh,
                        $offset,
                        function (OrnamentResolver $r) use (
                            $resolvers,
                            $resolver,
                            $scheduler,
                            $prepareInvokable,
                            $invokeCallback,
                            $enqueue,
                        ) {
                            // Get AsyncConfig from resolver (if present)
                            $asyncConfig = $resolver->asyncConfig;

                            $wrapper = function () use (
                                $resolver,
                                $r,
                                $prepareInvokable,
                                $invokeCallback,
                                $scheduler
                            ) {
                                $callbackParams = new Params\Callback(
                                    $resolver->region,
                                    $resolver->resolver,
                                    $r
                                );
                                /**
                                 * Run any middleware that modifies callback functions
                                 */
                                $callback = $prepareInvokable->call($callbackParams);

                                /**
                                 * Construct a new callback with the modified function
                                 */
                                $callbackParams = new Params\Callback(
                                    $resolver->region,
                                    $callback,
                                    $r
                                );
                                /**
                                 * Now actually execute it via another chain.
                                 * Our own middleware in the InvokeCallback chain will enqueue
                                 * and track the resolver from now on
                                 */
                                $invokeCallback->call($callbackParams);
                                $task = $scheduler->getLastAddedTask();
                                $hasFinished = false;
                                $scheduler->onComplete($task, function () use (&$hasFinished) {
                                    $hasFinished = true;
                                });
                                while (!$hasFinished) {
                                    yield;
                                }

                                return $task->getReturn();
                            };

                            // If AsyncConfig is provided, use scheduler directly with config
                            // Otherwise, use old Enqueue chain path for backward compatibility
                            if ($asyncConfig !== null) {
                                $task = $scheduler->enqueue($wrapper(), $asyncConfig, $resolver->resolver);
                            } else {
                                $task = $enqueue->call(new AsyncParams\EnqueueParams($resolver->region, $wrapper()));
                            }

                            $scheduler->onComplete($task, function () use ($r, $task) {
                                $r->resolve($task->getReturn());
                            });
                        }
                    );
                }
                $observers[$metaParams->region] = $ornaments;

                return $mesh;
            }
        );
    }

    private function setupAsyncContextMethods(
        ?BoundAccess $boundAccess,
        ?Chains\Get $getChain,
        ?Chains\Set $setChain,
    ): void {
        if (empty(array_filter(func_get_args()))) {
            return;
        }
        $boundAccess?->link(function (BoundAccessParams $params, callable $next) use ($getChain) {
            if ($params->type !== BoundAccessParams::TYPE_METHOD) {
                return $next($params);
            }
            switch ($params->name) {
                //case 'set':
                //    $args = $params->payload;
                //    $key = array_shift($args);
                //    $value = array_shift($args);
                //    $setChain->call(
                //        new Params\Set(
                //            $params->region,
                //            $key,
                //            $value
                //        )
                //    );
                //
                //    return null;
                case 'getAsync':
                    $args = $params->payload;
                    $key = array_shift($args);
                    $result = $getChain->call(
                        new Params\Get(
                            $params->region,
                            $key,
                        )
                    );

                    return Call::call(function () {
                    });
                //case 'dispatch':
                //    $args = $params->payload;
                //    $trigger = array_shift($args);
                //    $params->region->trigger($trigger, true);
                //
                //    return null;

                default:
                    return $next($params);
            }
        });
    }

    private function getCoroutineSchedulerForRegion(
        \SplObjectStorage $coroutinesByRegion,
        Region $region
    ): CoroutineScheduler {
        if (!$coroutinesByRegion->contains($region)) {
            $coroutinesByRegion->attach($region, new CoroutineScheduler());
        }

        return $coroutinesByRegion[$region];
    }

    /**
     * Converts an async guard from YAML format to AddCallback BuildStep format.
     */
    private function convertAsyncGuardToAddCallback(array $guard, string $stateName, string $target): array
    {
        // Convert async configuration from YAML to AsyncConfig object
        $priority = Priority::NORMAL;
        if (isset($guard['async']['priority'])) {
            $priority = match ($guard['async']['priority']) {
                'low' => Priority::LOW,
                'normal' => Priority::NORMAL,
                'high' => Priority::HIGH,
                default => Priority::NORMAL,
            };
        }

        $asyncConfig = new AsyncConfig(
            priority: $priority,
            singleton: $guard['async']['singleton'] ?? false,
            timeout: $guard['async']['timeout'] ?? null,
            debounce: $guard['async']['debounce'] ?? null,
            throttle: $guard['async']['throttle'] ?? null,
        );

        return [
            'buildStep' => 'AddCallback',
            'type' => $this->mapCallbackTypeToEvent($callbackType),
            'state' => $stateName,
            'target' => $target,
            'callback' => $guard['run'],
            'metadata' => $asyncConfig,
        ];
    }

    private function enqueueForRegion(
        \SplObjectStorage $coroutinesByRegion,
        Region $region,
        \Generator $coroutine,
        int $id
    ): void {
        $this->getCoroutineSchedulerForRegion($coroutinesByRegion, $region)->enqueue($coroutine, $id);
    }
}
