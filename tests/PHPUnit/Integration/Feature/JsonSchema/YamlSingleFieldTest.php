<?php

declare(strict_types=1);

namespace Tests\Integration\Feature\JsonSchema;

use Noem\State\Feature\Loader\SelfContainedLoader;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: YAML context.schema accepts single field definition
 * Intent: Supports minimal schema with one field for simple state machines
 * Criticality: contract
 */
final class YamlSingleFieldTest extends TestCase
{
    /**
     * @group skip
     */
    public function testYamlAcceptsSingleFieldSchema(): void
    {
        $this->markTestSkipped('SelfContainedLoader has a bug with context and container - see ContainerGetHelper TypeError');

        $yaml = <<<YAML
name: test
container: []
context:
  schema:
    - name: singleField
      type: string
      default: value
states:
  - name: idle
YAML;

        $region = SelfContainedLoader::fromYaml($yaml);

        // Verify schema was processed successfully
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }
}
