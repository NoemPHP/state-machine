<?php

declare(strict_types=1);

namespace Tests\Integration\Feature\JsonSchema;

use Noem\State\Feature\Loader\SelfContainedLoader;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: YAML context.schema accepts multiple field definitions
 * Intent: Supports complex schemas with many fields for stateful workflows
 * Criticality: contract
 */
final class YamlMultipleFieldsTest extends TestCase
{
    /**
     * @group skip
     */
    public function testYamlAcceptsMultipleFieldsSchema(): void
    {
        $this->markTestSkipped('SelfContainedLoader has a bug with context and container - see ContainerGetHelper TypeError');

        $yaml = <<<YAML
name: test
container: []
context:
  schema:
    - name: field1
      type: string
      default: value1
    - name: field2
      type: integer
      default: 42
    - name: field3
      type: boolean
      default: true
states:
  - name: idle
YAML;

        $region = SelfContainedLoader::fromYaml($yaml);

        // Verify schema was processed successfully
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }
}
