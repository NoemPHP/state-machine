<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\LoaderConfig;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\LoaderConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoaderConfig.states() returns empty array when states not defined
 */
#[Group('config-accessor'), Group('loader-config-accessor')]
class StatesReturnsEmptyTest extends TestCase
{
    public function testStatesReturnsEmptyWhenNotDefined(): void
    {
        $builder = new RegionBuilder();
        $config = ['loader' => ['array' => []]];
        $params = new BuildParams($builder, $config);
        
        $loaderConfig = $params->config(LoaderConfig::class);
        
        $this->assertSame([], $loaderConfig->states());
    }
}
