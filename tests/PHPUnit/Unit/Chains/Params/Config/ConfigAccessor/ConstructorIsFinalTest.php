<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\Config\ConfigAccessor;

use Noem\State\Chains\Params\Config\ConfigAccessor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConfigAccessor constructor is final to prevent signature breaking
 */
#[Group('config-accessor'), Group('config-accessor-base')]
class ConstructorIsFinalTest extends TestCase
{
    public function testConstructorIsFinal(): void
    {
        $reflection = new \ReflectionClass(ConfigAccessor::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertTrue($constructor->isFinal(), 'Constructor must be final to prevent signature breaking');
    }
}
