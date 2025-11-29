<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\ConfigAccessor;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\ConfigAccessor;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConfigAccessor calls initialize() template method after construction
 */
#[Group('config-accessor'), Group('config-accessor-base')]
class CallsInitializeTest extends TestCase
{
    public function testCallsInitialize(): void
    {
        $builder = new RegionBuilder();
        $config = [];
        $params = new BuildParams($builder, $config);
        
        $accessor = $params->config(InitializeTrackingAccessor::class);
        
        $this->assertTrue($accessor->initializeCalled());
    }
}

class InitializeTrackingAccessor extends ConfigAccessor
{
    private bool $initialized = false;
    
    protected function initialize(): void
    {
        $this->initialized = true;
    }
    
    public function initializeCalled(): bool
    {
        return $this->initialized;
    }
}
