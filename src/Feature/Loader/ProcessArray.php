<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader;

use Closure;
use Nette\Schema\Elements\Type;
use Nette\Schema\Expect;
use Nette\Schema\Message;
use Nette\Schema\Processor;
use Nette\Schema\ValidationException;
use Noem\State\Connection;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\RegionBuilder;

class ProcessArray
{
    public function __construct(
        private readonly Schema $schema,
        private readonly TransformArray $transformArray
    ) {
    }

    public function fromData(array $data, RegionBuilder $builder): RegionBuilder
    {
        /**
         * If a sub-region is being built, we need a separate builder for it since the current builder is already
         * dedicated to the parent region.
         * However, the middleware configuration needs to be passed on, which is why the new instance must be
         * received from an existing one.
         */
        static $recursion;
        if ($recursion) {
            $builder = $builder->newInstance();
        }
        $array = (array)$this->transformArray->call($data);

        $this->assertValidSchema($array);
        [$states, $regions, $transitions, $callbacks] = $this->extractConfig($array['states'] ?? []);

        $builder->setStates(...$states);
        foreach ($regions as $state => $subRegions) {
            foreach ($subRegions as $region) {
                $data = $region;
                $subRegion = $builder->newInstance()->build([
                    'loader' => [
                        'array' => $data,
                    ],
                ]);
                $builder->connect(
                    $subRegion,
                    Connection::DYNAMIC
                    | Connection::RECEIVE_EVENTS
                    | Connection::RECEIVE_ACTIONS
                    | Connection::RECEIVE_META,
                    fn(Connection $c) => $c->local->currentState() === $state
                );
            }
        }
        foreach ($transitions as $state => $stateTransitions) {
            foreach ($stateTransitions as $transition) {
                $builder->pushTransition($state, $transition['target'], $this->createTransitionGuard($transition));
            }
        }
        foreach ($callbacks['onEnter'] as $state => $stateCallbacks) {
            foreach ($stateCallbacks as $stateCallback) {
                $builder->onEnter($state, $this->createStateCallback($stateCallback));
            }
        }
        foreach ($callbacks['onExit'] as $state => $stateCallbacks) {
            foreach ($stateCallbacks as $stateCallback) {
                $builder->onExit($state, $this->createStateCallback($stateCallback));
            }
        }
        foreach ($callbacks['action'] as $state => $stateCallbacks) {
            foreach ($stateCallbacks as $stateCallback) {
                $builder->onAction($state, $this->createStateCallback($stateCallback));
            }
        }
        isset($array['initial']) && $builder->markInitial($array['initial']);
        isset($array['final']) && $builder->markFinal($array['final']);
        isset($array['inherits']) && $builder->inherits($array['inherits']);
        isset($array['factory']) && $builder->setFactory($this->createFactoryCallback($array['factory']));

        return $builder;
    }

    public function assertValidSchema(array $data): void
    {
        $callback = Expect::anyOf(
            Expect::string(),
            Expect::type(Closure::class),
        );
        $action = Expect::structure([
            'run' => $callback,
        ]);
        $transition = Expect::structure([
            'target' => Expect::string()->required(),
            'guard' => $callback,
        ]);

        $state = Expect::structure([
            'name' => Expect::string()->required(),
            'transitions' => Expect::listOf($transition),
            'onEnter' => Expect::listOf($action),
            'onExit' => Expect::listOf($action),
            'action' => Expect::listOf($action),

        ]);
        $region = Expect::structure([
            'label' => Expect::string(),
            'inherits' => Expect::listOf(new Type('string')),
            'initial' => Expect::string(),
            'states' => Expect::listOf($state),
            'final' => Expect::string(),
            'factory' => $callback,
        ]);
        $schemaContext = new SchemaContext(
            $callback,
            $action,
            $state,
            $region
        );

        //$schema = Expect::arrayOf($regionSchema);

        try {
            $this->schema->withProvider(function (SchemaContext $context) use ($data) {
                $processor = new Processor();
                $processor->process($context->region, $data);
            })->call(
                $schemaContext
            );
        } catch (ValidationException $e) {
            throw new \RuntimeException(
                'Invalid schema:' . PHP_EOL .
                implode(
                    PHP_EOL,
                    array_map(fn(Message $m) => $m->toString(), $e->getMessageObjects())
                )
            );
        }
    }

    /**
     * Creates a closure to be used as transition guard based on given definition.
     *
     * @param array $transition An array defining a single transition with a potential `guard` property
     *
     * @return Closure A callback suitable for use as a transition guard
     */
    public function createTransitionGuard(array $transition): Closure
    {
        if (!isset($transition['guard'])) {
            return fn(object $t): bool => true;
        }
        $guard = $transition['guard'];
        if (is_callable($guard)) {
            return $guard(...);
        }
        throw new \RuntimeException('Invalid "guard" callback');
    }

    /**
     * Creates a closure to be used as a callback during specific events such as entering/exiting a state or handling
     * actions.
     *
     * @param array $definition Definition of a state callback event handler
     *
     * @return Closure A callback suitable for use as the specified event handler
     */
    public function createStateCallback(array $definition): Closure
    {
        $run = $definition['run'];
        if (is_callable($run)) {
            /**
             * The clone here is important for some edge cases.
             * Asynchronous coroutines require a connection between the scheduled coroutine task
             * and its originating generator function.
             * If two Regions share the same Closure, this could prevent coroutines from being
             * enqueued properly.
             * Hence, we ensure that each callback is unique
             */
            return clone $run(...);
        }
        throw new \RuntimeException('Invalid "run" callback');
    }

    public function createFactoryCallback(string $definition): Closure
    {
        if (is_callable($definition)) {
            return $definition(...);
        }
        throw new \RuntimeException('Invalid factory');
    }

    /**
     * Extracts the relevant configuration data from a list of state definitions.
     *
     * @param array $raw An array representing states' configurations
     *
     * @return array Returns an array composed by `states`, `regions`, `transitions`, and `callbacks` extracted from
     *     provided input
     */
    public function extractConfig(array $raw): array
    {
        $states = [];
        $regions = [];
        $transitions = [];
        $callbacks = [
            'onEnter' => [],
            'onExit' => [],
            'action' => [],
        ];
        foreach ($raw as $value) {
            $states[] = $value['name'];
            if (isset($value['regions'])) {
                $regions[$value['name']] = $value['regions'];
            }
            if (isset($value['transitions'])) {
                $transitions[$value['name']] = $value['transitions'];
            }
            if (isset($value['onEnter'])) {
                $callbacks['onEnter'][$value['name']] = $value['onEnter'];
            }
            if (isset($value['onExit'])) {
                $callbacks['onExit'][$value['name']] = $value['onExit'];
            }
            if (isset($value['action'])) {
                $callbacks['action'][$value['name']] = $value['action'];
            }
        }

        return [$states, $regions, $transitions, $callbacks];
    }
}
