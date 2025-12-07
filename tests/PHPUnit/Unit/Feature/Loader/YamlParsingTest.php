<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\ConvertYaml;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConvertYaml parses valid YAML string into array
 */
#[Group('loader')]
#[Group('yaml-conversion')]
class YamlParsingTest extends TestCase
{
    public function testParsesValidYamlString(): void
    {
        $yaml = <<<YAML
        states:
          - name: idle
          - name: active
        initial: idle
        YAML;

        $converter = new ConvertYaml();
        $result = $converter->fromString($yaml, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('states', $result);
        $this->assertArrayHasKey('initial', $result);
        $this->assertEquals('idle', $result['initial']);
        $this->assertCount(2, $result['states']);
        $this->assertEquals('idle', $result['states'][0]['name']);
        $this->assertEquals('active', $result['states'][1]['name']);
    }
}
