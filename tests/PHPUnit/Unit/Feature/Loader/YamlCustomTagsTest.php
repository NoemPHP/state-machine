<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\ConvertYaml;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Acceptance Criterion: ConvertYaml supports YAML custom tag parsing via Symfony component
 */
#[Group('loader')]
#[Group('yaml-conversion')]
class YamlCustomTagsTest extends TestCase
{
    public function testSupportsCustomTagParsing(): void
    {
        $yaml = <<<YAML
        value: !custom "data"
        YAML;

        $helperCalled = false;
        $receivedValue = null;

        $helpers = [
            'custom' => function (string $value) use (&$helperCalled, &$receivedValue) {
                $helperCalled = true;
                $receivedValue = $value;
                return "processed_$value";
            }
        ];

        $converter = new ConvertYaml();
        $result = $converter->fromString($yaml, $helpers);

        // Verify the helper was called (which means TaggedValue was created)
        $this->assertTrue($helperCalled, 'Helper should have been called');
        $this->assertEquals('data', $receivedValue, 'Helper should receive the tagged value');
        $this->assertEquals('processed_data', $result['value']);
    }
}
