<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Loader;

use Noem\State\Feature\Loader\ConvertYaml;
use Noem\State\Feature\Loader\YamlHelpers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConvertYaml retrieves helpers from YamlHelpers registry
 */
#[Group('loader'), Group('yaml-helpers')]
class ConvertYamlUsesRegistryTest extends TestCase
{
    public function testRetrievesHelpersFromRegistry(): void
    {
        // Arrange
        $registry = new YamlHelpers();
        $registry->register('test', fn(string $value) => "processed: {$value}");

        $converter = new ConvertYaml($registry);
        $yaml = "value: !test 'hello'";

        // Act
        $result = $converter->fromString($yaml);

        // Assert
        $this->assertEquals(['value' => 'processed: hello'], $result);
    }
}
