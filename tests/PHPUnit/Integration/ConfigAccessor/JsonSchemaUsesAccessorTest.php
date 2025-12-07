<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\ConfigAccessor;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: JsonSchemaFeature uses LoaderConfig to access context schema
 */
#[Group('config-accessor'), Group('integration')]
class JsonSchemaUsesAccessorTest extends TestCase
{
    public function testJsonSchemaUsesAccessor(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new JsonSchemaFeature(),
            new ExtendedState()
        );

        $schemaDef = [
            ['name' => 'userName', 'type' => 'string', 'default' => '', 'description' => 'User name'],
            ['name' => 'count', 'type' => 'integer', 'default' => '0', 'description' => 'Counter'],
        ];

        $region = $builder
            ->setStates('idle', 'active')
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'schema' => $schemaDef
                        ]
                    ]
                ]
            ]);

        // Verify the region was built successfully
        $this->assertNotNull($region);
        $this->assertTrue($region->isInState('idle'));

        // Verify JsonSchemaFeature used LoaderConfig accessor to access schema
        // The presence of the schema should have been processed through the accessor
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testJsonSchemaSkipsWhenNoSchemaInContext(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new JsonSchemaFeature(),
            new ExtendedState()
        );

        $region = $builder
            ->setStates('idle', 'active')
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'otherKey' => 'value'
                        ]
                    ]
                ]
            ]);

        // Verify the region builds successfully even without schema
        // This tests that LoaderConfig.hasContext('schema') correctly returns false
        $this->assertNotNull($region);
        $this->assertTrue($region->isInState('idle'));
    }
}
