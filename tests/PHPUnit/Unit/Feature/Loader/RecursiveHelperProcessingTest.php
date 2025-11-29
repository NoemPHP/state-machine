<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\ConvertYaml;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConvertYaml recursively processes nested arrays with TaggedValue instances
 */
#[Group('loader')]
#[Group('yaml-conversion')]
class RecursiveHelperProcessingTest extends TestCase
{
    public function testProcessesHelpersRecursivelyInNestedArrays(): void
    {
        $yaml = <<<YAML
        states:
          - name: idle
            data:
              count: !double "5"
          - name: active
            nested:
              deep:
                value: !triple "3"
        YAML;
        
        $helpers = [
            'double' => fn(string $value) => (int)$value * 2,
            'triple' => fn(string $value) => (int)$value * 3,
        ];
        
        $converter = new ConvertYaml();
        $result = $converter->fromString($yaml, $helpers);
        
        // Check first level nesting
        $this->assertEquals(10, $result['states'][0]['data']['count']);
        
        // Check deep nesting
        $this->assertEquals(9, $result['states'][1]['nested']['deep']['value']);
    }
}
