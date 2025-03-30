<?php

declare(strict_types=1);

namespace Noem\State;

use ArrayAccess;
use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;

class RegionBuilder
{
    protected Events $events;

    protected array $states = [];

    protected array $transitions = [];

    protected array $cascadingContext = [];

    protected ?string $initial = null;

    protected ?string $final = null;

    protected \Closure $regionFactory;

    private(set) ChainMail $chainMail;

    private Chains\BuildRegion $buildChain;

    private Chains\Meta $meta;

    public function __construct(?ChainMail $chainMail = null)
    {
        if (!$chainMail) {
            $chainMail = new ChainMail();
            $chainMail->supply(
                fn(): RegionBuilder => $this,
                fn(): Chains\EnhanceRegionBuilder => new Chains\EnhanceRegionBuilder(),
                fn(): Chains\DispatchAction => new Chains\DispatchAction(),
                fn(Chains\InvokeCallback $i, PrepareInvokable $p): Chains\Guard => new Chains\Guard($i, $p),
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
        $this->meta = $this->chainMail->get(Chains\Meta::class);
        $this->buildChain = new Chains\BuildRegion();
    }

    public function addStep(callable $callback): self
    {
        $this->buildChain->link($callback);

        return $this;
    }

    public function enableFeatures(Feature ...$features): self
    {
        foreach ($features as $feature) {
            $feature($this->chainMail);
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
        Region $remoteRegion,
        int $flags = 0,
        ?callable $predicate = null
    ): self {
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
     * Pushes a transition from one state to another based on provided guard condition.
     *
     * @param string $from State that triggers this transition
     * @param string $to Target state after successful transition
     * @param ?\Closure $guard Guard callback returning true or false. Optional, allow by default
     *
     * @return self This builder instance, allowing chaining
     */
    public function pushTransition(string $from, string $to, ?\Closure $guard = null): self
    {
        $this->transitions[$from][$to][] = $guard ?? fn(object $t): bool => true;

        return $this;
    }

    /**
     * Specifies keys that should be inherited through multiple regions
     *
     * @param array $keys List of key names to inherit
     *
     * @return self This builder instance, allowing chaining
     */
    public function inherits(array $keys): self
    {
        $this->cascadingContext = $keys;

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
        MetaType $type,
        int $flags = 0,
        ?callable $predicate = null
    ): self {
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
    public function build(?bool $skipMiddlewares = false): Region
    {
        $this->chainMail->boot();
        $enhance = $this->chainMail->get(Chains\EnhanceRegionBuilder::class);

        $provider = function (RegionBuilder $builder) {
            $guardChain = $this->chainMail->get(Chains\Guard::class);
            $actionChain = $this->chainMail->get(Chains\DispatchAction::class);
            $connectionsChain = $this->chainMail->get(Chains\ConnectedRegions::class);
            $path = $this->chainMail->get(Chains\Path::class);
            $events = $this->chainMail->get(Events::class);
            $this->assertValidConfig();

            return new Region(
                states: $builder->states,
                transitions: $builder->transitions,
                events: $events,
                initial: $builder->initial ?? current($builder->states),
                final: $builder->final ?? end($builder->states),
                actionChain: $actionChain,
                guardChain: $guardChain,
                connectionsChain: $connectionsChain,
                path: $path
            );
        };
        $builder = $this;
        if (!$skipMiddlewares) {
            $builder = $enhance->call($this);
        }

        return $this->buildChain->withProvider($provider)->call($builder);
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
