<?php

namespace Noem\State\Feature\Loader;

use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\Helper\ContainerGetHelper;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Region;
use Noem\State\RegionBuilder;

abstract class Machine
{

    /**
     * @return Feature[]
     */
    public function features(): iterable
    {
        return [
            new RegionLoader(),
        ];
    }

    public function builderArgs(): array
    {
        return [
            'loader' => [
                'yaml' => $this->yaml(),
                'yamlHelpers' => $this->yamlHelpers(),
            ],
        ];
    }

    public function yamlHelpers(): array
    {
        return [
            'php' => new PhpEvalHelper(),
            'get' => new ContainerGetHelper($this->container()),
        ];
    }

    public function container(): iterable
    {
        return [];
    }

    public function execute(?Region $region = null): object
    {
        $region = $region ?? $this->region();
        while (!$region->isFinal()) {
            $last = $region->trigger($this->trigger());
        }

        return $last;
    }

    abstract public function trigger(): object;

    abstract public function yaml(): string;

    public static function run(self $machine): mixed
    {
        $builder = new RegionBuilder();

        $region = $builder
            ->enableFeatures(
                ...$machine->features()
            )
            ->build(
                $machine->builderArgs()
            );
        $last = null;
        while (!$region->isFinal()) {
            $last = $region->trigger($machine->trigger());
        }

        return $last;
    }
}
