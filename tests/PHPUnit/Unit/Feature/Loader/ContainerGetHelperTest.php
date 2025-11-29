<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Helper\ContainerGetHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ContainerGetHelper retrieves values from container by key
 */
#[Group('loader')]
#[Group('yaml-helpers')]
class ContainerGetHelperTest extends TestCase
{
    public function testRetrievesValuesFromContainerByKey(): void
    {
        $container = [
            'myService' => 'service value',
            'config' => ['setting' => 'value'],
        ];
        
        $helper = new ContainerGetHelper($container);
        
        $result = $helper('myService');
        
        $this->assertEquals('service value', $result);
    }
    
    public function testRetrievesMultipleValues(): void
    {
        $container = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];
        
        $helper = new ContainerGetHelper($container);
        
        $this->assertEquals('value1', $helper('key1'));
        $this->assertEquals('value2', $helper('key2'));
    }
}
