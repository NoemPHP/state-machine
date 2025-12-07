<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\ConvertYaml;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConvertYaml processes custom YAML tags using helpers
 */
#[Group('loader')]
#[Group('yaml-conversion')]
class YamlHelperProcessingTest extends TestCase
{
    public function testProcessesCustomTagsUsingHelpers(): void
    {
        $yaml = <<<YAML
        value: !double "5"
        YAML;

        $helpers = [
            'double' => fn(string $value) => (int)$value * 2,
        ];

        $converter = new ConvertYaml();
        $result = $converter->fromString($yaml, $helpers);

        $this->assertArrayHasKey('value', $result);
        $this->assertEquals(10, $result['value']);
    }
}
