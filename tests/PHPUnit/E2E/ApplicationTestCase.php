<?php

declare(strict_types=1);

namespace Noem\State\Test\E2E;

use Noem\State\Feature\Loader\Helper\ContainerGetHelper;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Region;
use Noem\State\Test\Integration\RegionBuilderTestCase;

abstract class ApplicationTestCase extends RegionBuilderTestCase
{

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
}
