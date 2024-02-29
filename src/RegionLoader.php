<?php

namespace Noem\State;

use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

class RegionLoader
{

    public static function fromYaml(string $yaml): RegionBuilder
    {
        $array = Yaml::parse($yaml);

        return self::fromArray($array);
    }

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

        return $builder;
    }

    public static function createTransitionGuard(array $transition): \Closure
    {
        if (!isset($transition['guard'])) {
            return fn() => true;
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

    public static function createStateCallback(string|TaggedValue $guard)
    {
        if ($guard instanceof TaggedValue) {
            if ($guard->getTag() === 'php') {
                return eval($guard->getValue());
            }

            if ($guard->getTag() === 'get') {
                //TODO access container
            }
        }
        if (is_callable($guard)) {
            return \Closure::fromCallable($guard);
        }
        throw new \RuntimeException('Invalid guard');
    }

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
                $callbacks['onEnter'][$value['name']][] = $value['onEnter'];
            }
            if (isset($value['onExit'])) {
                $callbacks['onExit'][$value['name']][] = $value['onExit'];
            }
            if (isset($value['action'])) {
                $callbacks['action'][$value['name']][] = $value['action'];
            }
        }

        return [$states, $regions, $transitions, $callbacks];
    }
}
