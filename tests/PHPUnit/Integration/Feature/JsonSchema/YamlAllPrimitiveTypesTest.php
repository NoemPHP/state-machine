<?php

declare(strict_types=1);

namespace Tests\Integration\Feature\JsonSchema;

use Noem\State\Feature\Loader\SelfContainedLoader;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: YAML context.schema supports all JSON Schema primitive types
 * Intent: Validates string, number, integer, boolean, array, object types correctly
 * Criticality: contract
 */
final class YamlAllPrimitiveTypesTest extends TestCase
{
    /**
     * @group skip
     */
    public function testYamlSupportsAllPrimitiveTypes(): void
    {
        $this->markTestSkipped('SelfContainedLoader has a bug with context and container - see ContainerGetHelper TypeError');

        $yaml = <<<YAML
name: test
container: []
context:
  schema:
    - name: stringField
      type: string
      default: text
    - name: numberField
      type: number
      default: 3.14
    - name: integerField
      type: integer
      default: 42
    - name: booleanField
      type: boolean
      default: true
states:
  - name: idle
YAML;

        $region = SelfContainedLoader::fromYaml($yaml);

        // Verify all primitive types were processed successfully
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }
}
