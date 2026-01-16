<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\ResponseFormat;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ResponseFormat accepts format type and definition
 */
#[Group('ai'), Group('response-format')]
class AcceptsFormatAndDefinitionTest extends TestCase
{
    #[Test]
    public function acceptsFormatAndDefinitionTest(): void
    {
        $definition = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]];

        $responseFormat = new \Noem\State\Feature\Ai\ResponseFormat('json', $definition);

        $this->assertSame('json', $responseFormat->format, 'Format should be stored correctly');
        $this->assertSame($definition, $responseFormat->definition, 'Definition should be stored correctly');
    }
}
