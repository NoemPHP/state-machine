<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Helper\ContainerGetHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ContainerGetHelper initializes with empty container when none provided
 */
#[Group('loader')]
#[Group('yaml-helpers')]
class ContainerGetHelperDefaultTest extends TestCase
{
    public function testInitializesWithEmptyContainer(): void
    {
        $helper = new ContainerGetHelper();
        
        // Should not throw an exception when created without container
        $this->assertInstanceOf(ContainerGetHelper::class, $helper);
    }
    
    public function testDefaultContainerReturnsNullForNonExistentKeys(): void
    {
        $helper = new ContainerGetHelper();
        
        // Default empty container returns null for non-existent keys
        $result = $helper('nonExistentKey');
        
        $this->assertNull($result);
    }
}
