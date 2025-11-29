<?php

declare(strict_types=1);

namespace Noem\State;

use ArrayAccess;
use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\DoTransition;
use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Feature;
use Noem\State\Feature\FeatureRegistry;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;

class RegionBuilder
{
    protected Events $events;

    protected array $states = [];

    protected array $transitions = [];

    protected ?string $initial = null;

    protected ?string $final = null;

    protected \Closure $regionFactory;

    // TODO: Make this property private in a future iteration
    // Currently it's exposed as contract because Features and BuildSteps need access to ChainMail
    // to register/retrieve services. Making it private would require refactoring ~50+ callsites
    // and potentially introducing a different dependency injection pattern for BuildSteps.
    // See: https://github.com/NoemPHP/state-machine/issues/XXX (create issue if pursuing this)
    private(set) ChainMail $chainMail;

    private Chains\BuildRegion $buildChain;

    private Chains\Meta $meta;

    public function __construct(?ChainMail $chainMail = null)
    {
        if (!$chainMail) {
            $chainMail = new ChainMail();
            $chainMail->supply(
                fn(): RegionBuilder => $this,
                fn(): FeatureRegistry => new FeatureRegistry(),
                fn(): Chains\EnhanceRegionBuilder => new Chains\EnhanceRegionBuilder(),
                fn(ConnectedRegions $c, Events $e): Chains\DispatchAction => new Chains\DispatchAction($c, $e),
                fn(): Chains\ValidateCallback => new Chains\ValidateCallback(),
                fn(): Chains\PrepareInvokable => new Chains\PrepareInvokable(),
                fn(): Chains\InvokeCallback => new Chains\InvokeCallback(),
                fn(): Chains\ConnectedRegions => new Chains\ConnectedRegions(),
                fn(): Chains\ExtendedState => new Chains\ExtendedState(),
                fn(Chains\ConnectedRegions $connectedRegions): Chains\Meta => new Chains\Meta($connectedRegions),
                fn(): Chains\Set => new Chains\Set(),
                fn(): Chains\Get => new Chains\Get(),
                Events::conjure(),
                fn(Chains\ConnectedRegions $connections): Chains\Path => new Chains\Path($connections)
            );
        }
        $this->chainMail = $chainMail;

        // Ensure FeatureRegistry is always available, even with custom ChainMail
        try {
            $this->chainMail->get(FeatureRegistry::class);
        } catch (\Noem\State\Middleware\ChainException) {
            $this->chainMail->supply(fn(): FeatureRegistry => new FeatureRegistry());
        }

        $this->meta = $this->chainMail->get(Chains\Meta::class);
        $this->buildChain = new Chains\BuildRegion();
        /**
         * Making transitions optional is not fully thought through yet.
         * So while it is maintained "externally" mainly for separation of concerns,
         * it is still core functionality
         */
        $this->enableFeatures(new TransitionsFeature());
    }

    private function addStep(callable $callback): self
    {
        $this->buildChain->link($callback);

        return $this;
    }

    public function addBuildStep(BuildStep $step): self
    {
        $this->addStep($step->callback(...));
        return $this;
    }

    public function enableFeatures(Feature ...$features): self
    {
        $registry = $this->chainMail->get(FeatureRegistry::class);

        foreach ($features as $feature) {
            $registry->register($feature);
        }

        return $this;
    }

    /**
     * Return a new builder with the current middleware configuration
     *
     * @return $this
     */
    public function newInstance(): self
    {
        return new $this($this->chainMail);
    }

    /**
     * Sets an array as list of available states
     *
     * @param array $states An array containing all possible states
     *
     * @return self This builder instance, allowing chaining
     */
    public function setStates(string ...$states): self
    {
        $this->states = $states;

        return $this;
    }

    public function addState(string $state): self
    {
        $this->states[] = $state;

        return $this;
    }

    /**
     * Connect a region to the current builder using a connection type.
     * This allows for nested state machines and hierarchical state management.
     * However, note that this method only registers a connection in a declarative fashion.
     * It is up to the middleware configuration to enact the connection.
     *
     * @param Region $remoteRegion
     * @param int $flags
     * @param callable|null $predicate
     *
     * @return $this
     */
    public function connect(
        Region    $remoteRegion,
        int       $flags = 0,
        ?callable $predicate = null
    ): self
    {
        $this->buildChain->link(
            function (RegionBuilder $builder, callable $next) use ($remoteRegion, $flags, $predicate) {
                $connectedRegions = $this->chainMail->get(ConnectedRegions::class);
                assert($connectedRegions instanceof ConnectedRegions);
                $region = $next($builder);

                $connection = new Connection($region, $remoteRegion, $flags, $predicate);
                $connectedRegions->addConnection($connection);

                return $region;
            }
        );

        return $this;
    }

    /**
     * Registers action event handlers per state
     *
     * @param string $state Name of the state where the handler should apply
     * @param \Closure $callback Action handler callback
     *
     * @return self This builder instance, allowing chaining
     */
    public function onAction(string $state, \Closure $callback): self
    {
        $this->buildChain->link(
            function (RegionBuilder $builder, callable $next) use ($state, $callback) {
                $events = $this->chainMail->get(Events::class);
                $region = $next($builder);
                $events->addActionHandler($region, $state, $callback);

                return $region;
            }
        );

        return $this;
    }

    public function onEnter(string $state, \Closure $callback): self
    {
        $this->buildChain->link(
            function (RegionBuilder $builder, callable $next) use ($state, $callback) {
                $events = $this->chainMail->get(Events::class);
                $region = $next($builder);
                $events->addEnterStateHandler($region, $state, $callback);

                return $region;
            }
        );

        return $this;
    }

    public function onExit(string $state, \Closure $callback): self
    {
        $this->buildChain->link(
            function (RegionBuilder $builder, callable $next) use ($state, $callback) {
                $events = $this->chainMail->get(Events::class);
                $region = $next($builder);
                $events->addExitStateHandler($region, $state, $callback);

                return $region;
            }
        );

        return $this;
    }

    /**
     * Sets metadata for the region being built.
     *
     * This method allows adding arbitrary key-value pairs to the region's metadata,
     * which can be used for various purposes such as configuration, identification,
     * or additional context. The metadata is stored within the region and can be
     * accessed through the appropriate chains.
     *
     * @param array|ArrayAccess $data The metadata to add for the region
     * @param MetaType $type
     * @param int $flags
     * @param callable|null $predicate
     *
     * @return self The current instance of the builder, allowing for method chaining.
     */
    public function setMetaData(
        array|ArrayAccess $data,
        MetaType          $type,
        int               $flags = 0,
        ?callable         $predicate = null
    ): self
    {
        $this->buildChain->link(
            function (RegionBuilder $regionBuilder, callable $next) use ($data, $type, $flags, $predicate) {
                $region = $next($regionBuilder);
                $this->meta->addRecord(new Record($region, $data, $type, $flags, $predicate));

                return $region;
            }
        );

        return $this;
    }

    /**
     * Marks the specified state as the starting point (initial) for a newly created Region
     *
     * @param string $state Starting state name
     *
     * @return self This builder instance, allowing chaining
     */
    public function markInitial(string $state): self
    {
        $this->initial = $state;

        return $this;
    }

    /**
     * Designates the final destination state for a newly generated Region
     *
     * @param string $state Final target state
     *
     * @return self This builder instance, allowing chaining
     */
    public function markFinal(string $state): self
    {
        $this->final = $state;

        return $this;
    }

    public function setFactory(callable $factory): self
    {
        $this->regionFactory = $factory;

        return $this;
    }

    /**
     * Builds a new `Region` object using configured settings
     *
     * @return Region Newly built Region instance
     */
    public function build(Mesh|iterable|null $featureArgs = null, ?bool $skipMiddlewares = false): Region
    {
        $featureRegistry = $this->chainMail->get(FeatureRegistry::class);
        // Resolve features and invoke them with this ChainMail instance
        // Features are invoked exactly once per ChainMail, preventing duplicate
        // middleware registration when builders share ChainMail via newInstance()
        $featureRegistry->resolve($this->chainMail);

        $this->chainMail->boot();
        $enhance = $this
            ->chainMail
            ->get(Chains\EnhanceRegionBuilder::class)
            ->withProvider(fn() => $this);

        $provider = function (RegionBuilder $builder) {
            $actionChain = $this->chainMail->get(Chains\DispatchAction::class);
            $transitionChain = $this->chainMail->get(DoTransition::class);
            $path = $this->chainMail->get(Chains\Path::class);
            $events = $this->chainMail->get(Events::class);

            // Validate the enhanced builder (which may have states set by features like RegionLoader)
            if (empty($builder->states)) {
                throw new \RuntimeException("States cannot be empty");
            }

            $region = new Region(
                transitionChain: $transitionChain,
                events: $events,
                initial: $builder->initial ?? current($builder->states),
                final: $builder->final ?? end($builder->states),
                actionChain: $actionChain,
                path: $path
            );

            return $region;
        };
        $builder = $this;
        if (!$skipMiddlewares) {
            $builder = $enhance->call(new BuildParams($this, $featureArgs));
        }

        $region = $this->buildChain->withProvider($provider)->call($builder);

        // Initial state's onEnter should be called on first trigger, not during build
        // This ensures consistent lifecycle: all state entries (including initial) go through the same pathway

        return $region;
    }

    protected function assertValidConfig(): void
    {
        if (empty($this->states)) {
            throw new \RuntimeException("States cannot be empty");
        }
        if (count($this->states) > 1) {
        }
    }
}
