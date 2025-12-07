<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\BuildParams;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\ConfigAccessor;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: BuildParams provides config() method that returns typed accessor instances
 */
#[Group('config-accessor'), Group('build-params-infrastructure')]
class ProvidesConfigMethodTest extends TestCase
{
    public function testProvidesConfigMethod(): void
    {
        $builder = new RegionBuilder();
        $config = ['test' => 'value'];
        $params = new BuildParams($builder, $config);

        $accessor = $params->config(TestAccessor::class);

        $this->assertInstanceOf(TestAccessor::class, $accessor);
        $this->assertInstanceOf(ConfigAccessor::class, $accessor);
    }
}

class TestAccessor extends ConfigAccessor
{
    public function testValue(): mixed
    {
        return $this->get('test');
    }
}
