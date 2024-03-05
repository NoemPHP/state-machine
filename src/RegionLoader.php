<?php

namespace Noem\State;

use Closure;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;
use Nette\Schema\Elements\Type;
use Nette\Schema\Expect;
use Nette\Schema\Message;
use Nette\Schema\Processor;
use Nette\Schema\ValidationException;

/**
 * The Noem State Machine's RegionLoader class is responsible for loading and parsing
 * state machine configurations from YAML or PHP arrays.
 * It contains methods to resolve helper functions, extract configuration data
 * from state definitions, and create callbacks for transition guards and
 * event handlers like entering/exiting states or handling actions.
 * It can load configurations from YAML input using the fromYaml() method
 * or from PHP arrays using the fromArray() method.
 */
class RegionLoader
{

    public function __construct(private readonly array $helpers)
    {
    }

    /**
     * Resolves a helper function based on the given name and content.
     *
     * @param string $name The name of the helper function to resolve.
     * @param string $content The content to be passed to the helper function.
     *
     * @return mixed The result of the helper function when found, or throws an exception if the helper is undefined.
     *
     * @throws \RuntimeException
     */
    private function resolveHelper(string $name, string $content): mixed
    {
        if (isset($this->helpers[$name])) {
            return $this->helpers[$name]($content);
        }

        throw new \RuntimeException("Undefined helper '{$name}'");
    }

    /**
     * Load a region builder from YAML input.
     *
     * @param string $yaml The path or content of the YAML file containing the configuration data
     *
     * @return RegionBuilder Returns an instance of RegionBuilder initialized with data from given YAML input
     */
    public function fromYaml(string $yaml): RegionBuilder
    {
        $array = Yaml::parse(
            $yaml,
            Yaml::PARSE_CUSTOM_TAGS
        );

        return self::fromArray($array);
    }

    /**
     * Load a region builder from PHP associative arrays.
     *
     * @param array $array Configuration data stored in a nested array structure
     *
     * @return RegionBuilder Returns an instance of RegionBuilder initialized with data from given array input
     */
    public function fromArray(array $array): RegionBuilder
    {
        $this->assertValidSchema($array);
        $builder = new RegionBuilder();
        [$states, $regions, $transitions, $callbacks] = $this->extractConfig($array['states'] ?? []);
        $builder->setStates(...$states);
        foreach ($regions as $state => $subRegions) {
            foreach ($subRegions as $region) {
                $builder->addRegion($state, self::fromArray($region));
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

        return $builder;
    }

    public function assertValidSchema(array $data)
    {
        $callbackSchema = Expect::anyOf(
            Expect::string(),
            Expect::type(TaggedValue::class),
        );
        $actionSchema = Expect::structure([
            'run' => $callbackSchema,
        ]);
        $transitionSchema = Expect::structure([
            'target' => Expect::string()->required(),
            'guard' => $callbackSchema,
        ]);
        $nestedRegionSchema = new Type('list');

        $stateSchema = Expect::structure([
            'name' => Expect::string()->required(),
            'transitions' => Expect::listOf($transitionSchema),
            'onEnter' => Expect::listOf($actionSchema),
            'onExit' => Expect::listOf($actionSchema),
            'action' => Expect::listOf($actionSchema),
            'regions' => $nestedRegionSchema,
        ]);
        $regionSchema = Expect::structure([
            'label' => Expect::string(),
            'inherits' => Expect::listOf(new Type('string')),
            'initial' => Expect::string(),
            'states' => Expect::listOf($stateSchema),
            'final' => Expect::string(),

        ]);
        $nestedRegionSchema->items($regionSchema);
        //$schema = Expect::arrayOf($regionSchema);
        $processor = new Processor();
        try {
            $processor->process($regionSchema, $data);
        } catch (ValidationException $e) {
            throw new \RuntimeException(
                'Invalid schema:'.PHP_EOL.
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
        if ($guard instanceof TaggedValue) {
            return $this->resolveHelper($guard->getTag(), $guard->getValue());
        }
        if (is_callable($transition['guard'])) {
            return Closure::fromCallable($transition['guard']);
        }
        throw new \RuntimeException('Invalid guard');
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
        if ($run instanceof TaggedValue) {
            return $this->resolveHelper($run->getTag(), $run->getValue());
        }
        if (is_callable($run)) {
            return Closure::fromCallable($run);
        }
        throw new \RuntimeException('Invalid guard');
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
