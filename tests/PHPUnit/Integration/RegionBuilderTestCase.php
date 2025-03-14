<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use Noem\State\Chains\Meta;
use Noem\State\Chains\Params\Get;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

abstract class RegionBuilderTestCase extends TestCase
{

    protected RegionBuilder $builder;

    protected Meta $meta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new RegionBuilder();
        $this->meta = $this->builder->chainMail->use(
            function (Meta $meta) {
                return $meta;
            }
        );
    }

    protected function assertContext(
        Region $region,
        string $key,
        ?string $state,
        mixed $assumed,
        string $message = ''
    ): void {
        $mesh = $this->meta->call($region);
        $this->assertSame(
            $assumed,
            $mesh[$key],
            $message
        );
    }

    protected function assertRegionContext(Region $region, string $key, mixed $assumed, string $message = ''): void
    {
        $this->assertContext($region, $key, null, $assumed, $message);
    }

    protected function assertStateContext(
        Region $region,
        string $state,
        string $key,
        mixed $assumed,
        string $message = ''
    ): void {
        $this->assertContext($region, $key, $state, $assumed, $message);
    }
}

