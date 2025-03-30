<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use Noem\State\Chains\Meta;
use Noem\State\Chains\Params;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

abstract class RegionBuilderTestCase extends TestCase
{

    protected RegionBuilder $builder;

    protected Meta $meta {
        get {
            return $this->builder->chainMail->get(Meta::class);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new RegionBuilder();
    }

    protected function assertContext(
        Region $region,
        string $key,
        ?string $state,
        mixed $assumed,
        string $message = ''
    ): void {
        $metaParams = new Params\Meta($region, ContextMetaType::get());
        $mesh = $this->meta->call($metaParams);
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

