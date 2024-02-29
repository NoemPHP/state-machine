<?php

namespace Noem\State;

use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

class RegionLoader
{

    /**
     * Load a region builder from YAML input.
     *
     * @param string $yaml The path or content of the YAML file containing the configuration data
     *
     * @return RegionBuilder Returns an instance of RegionBuilder initialized with data from given YAML input
     */
    public static function fromYaml(string $yaml): RegionBuilder
    {
        $array = Yaml::parse($yaml);

        return self::fromArray($array);
    }

    /**
     * Load a region builder from PHP associative arrays.
     *
     * @param array $array Configuration data stored in a nested array structure
     *
     * @return RegionBuilder Returns an instance of RegionBuilder initialized with data from given array input
     */
    public static function fromArray(array $array): RegionBuilder
    {
        $builder = new RegionBuilder();
        [$states, $regions, $transitions, $callbacks] = self::extractConfig($array['states'] ?? []);
        $builder->setStates($states);
        foreach ($regions as $state => $subRegions) {
            foreach ($subRegions as $region) {
                $builder->addRegion($state, self::fromArray($region));
            }
        }
        foreach ($transitions as $state => $stateTransitions) {
            foreach ($stateTransitions as $transition) {
                $builder->pushTransition($state, $transition['target'], self::createTransitionGuard($transition));
            }
        }
        foreach ($callbacks['onEnter'] as $state => $stateCallbacks) {
            foreach ($stateCallbacks as $stateCallback) {
                $builder->onEnter($state, self::createStateCallback($stateCallback));
            }
        }
        foreach ($callbacks['onExit'] as $state => $stateCallbacks) {
            foreach ($stateCallbacks as $stateCallback) {
                $builder->onExit($state, self::createStateCallback($stateCallback));
            }
        }
        foreach ($callbacks['action'] as $state => $stateCallbacks) {
            foreach ($stateCallbacks as $stateCallback) {
                $builder->onAction($state, self::createStateCallback($stateCallback));
            }
        }
        isset($array['initial']) && $builder->markInitial($array['initial']);
        isset($array['final']) && $builder->markFinal($array['final']);
        isset($array['inherits']) && $builder->inherits($array['inherits']);

        return $builder;
    }

    /**
     * Creates a closure to be used as transition guard based on given definition.
     *
     * @param array $transition An array defining a single transition with a potential `guard` property
     *
     * @return \Closure A callback suitable for use as a transition guard
     */
    public static function createTransitionGuard(array $transition): \Closure
    {
        if (!isset($transition['guard'])) {
            return fn(object $t): bool => true;
        }
        $guard = $transition['guard'];
        if ($guard instanceof TaggedValue) {
            if ($guard->getTag() === 'php') {
                return eval($guard->getValue());
            }

            if ($guard->getTag() === 'get') {
                //TODO access container
            }
        }
        if (is_callable($transition['guard'])) {
            return \Closure::fromCallable($transition['guard']);
        }
        throw new \RuntimeException('Invalid guard');
    }

    /**
     * Creates a closure to be used as a callback during specific events such as entering/exiting a state or handling
     * actions.
     *
     * @param array $definition Definition of a state callback event handler
     *
     * @return \Closure A callback suitable for use as the specified event handler
     */
    public static function createStateCallback(array $definition): \Closure
    {
        $run = $definition['run'];
        if ($run instanceof TaggedValue) {
            if ($run->getTag() === 'php') {
                return eval($run->getValue());
            }

            if ($run->getTag() === 'get') {
                //TODO access container
            }
        }
        if (is_callable($run)) {
            return \Closure::fromCallable($run);
        }
        throw new \RuntimeException('Invalid guard');
    }

    /**
     * Extracts the relevant configuration data from a list of state definitions.
     *
     * @param array $array An array representing states' configurations
     *
     * @return array Returns an array composed by `states`, `regions`, `transitions`, and `callbacks` extracted from
     *     provided input
     */
    public static function extractConfig(array $raw): array
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
