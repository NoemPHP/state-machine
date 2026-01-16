<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\ResponseFormat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ResponseFormat stores format and definition as readonly properties
 */
#[Group('ai'), Group('response-format')]
class StoresReadonlyPropertiesTest extends TestCase
{
    #[Test]
    public function storesReadonlyPropertiesTest(): void
    {
        $definition = ['type' => 'string'];
        $responseFormat = new \Noem\State\Feature\Ai\ResponseFormat('text', $definition);

        // Verify properties are accessible
        $this->assertSame('text', $responseFormat->format);
        $this->assertSame($definition, $responseFormat->definition);

        // PHP will enforce readonly at compile time, so we can't test modification directly
        // But we can verify the reflection shows readonly
        $reflection = new \ReflectionClass($responseFormat);
        $formatProp = $reflection->getProperty('format');
        $definitionProp = $reflection->getProperty('definition');

        $this->assertTrue($formatProp->isReadOnly(), 'format should be readonly');
        $this->assertTrue($definitionProp->isReadOnly(), 'definition should be readonly');
    }
}
