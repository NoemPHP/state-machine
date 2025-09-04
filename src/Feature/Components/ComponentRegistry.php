<?php

namespace Noem\State\Feature\Components;

use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\Params\Callback;
use Noem\State\Region;
use SplObjectStorage;

/**
 *
 */
class ComponentRegistry
{
    /**
     * List of component names attached to any given region
     * @var SplObjectStorage<Region,string>
     */
    private SplObjectStorage $componentsByRegion;

    /**
     * Component names mapped to their defined initial data
     * @var array<string,array>
     */
    private array $componentData = [];
    /**
     * @var array<string,callable():
     */
    private array $componentSystems = [];


    public function __construct(private readonly InvokeCallback $invokeCallback)
    {

    }

    public function registerComponent(string $name, array $initialData, callable $system)
    {
        $this->componentData[$name] = $initialData;
        $this->componentSystems[$name] = $system;
    }

    public function addComponentToRegion(Region $region, string $componentName)
    {
        if (!isset($this->componentData[$componentName])) {
            $this->componentData[$componentName] = [];
        }
        $this->componentsByRegion[$region][] = $componentName;
    }

    public function addComponentToState(Region $region, string $state, string $componentName)
    {

    }


    public function executeComponentSystems(Region $region): callable
    {
        foreach ($this->componentsByRegion[$region] as $componentName) {
            $system = $this->componentSystems[$componentName];
            //TODO should this be an ExecuteComponentSystem Chain?
            $callback = new Callback($region, $system, new \stdClass());//TODO implement a custom object that carries the updated component data?
            $this->invokeCallback->call($callback);
        }
    }
}