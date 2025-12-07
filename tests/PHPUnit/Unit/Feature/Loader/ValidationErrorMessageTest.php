<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray throws RuntimeException with validation details for invalid schema
 */
#[Group('loader')]
#[Group('array-processing')]
class ValidationErrorMessageTest extends TestCase
{
    public function testThrowsRuntimeExceptionWithValidationDetails(): void
    {
        $schema = new Schema();
        $transformArray = new TransformArray();
        $processor = new ProcessArray($schema, $transformArray);

        $invalidConfig = [
            'states' => [
                // Missing required 'name' field
                ['invalid' => 'data'],
            ],
        ];

        $builder = new RegionBuilder();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid schema');

        $processor->fromData($invalidConfig, $builder);
    }

    public function testExceptionIncludesValidationDetails(): void
    {
        $schema = new Schema();
        $transformArray = new TransformArray();
        $processor = new ProcessArray($schema, $transformArray);

        $invalidConfig = [
            'states' => [
                ['invalid' => 'data'],
            ],
        ];

        $builder = new RegionBuilder();

        try {
            $processor->fromData($invalidConfig, $builder);
            $this->fail('Expected RuntimeException to be thrown');
        } catch (\RuntimeException $e) {
            // Verify the error message contains details about the validation failure
            $this->assertStringContainsString('Invalid schema', $e->getMessage());
            // The message should include specific field information
            $this->assertStringContainsString('name', $e->getMessage());
        }
    }
}
