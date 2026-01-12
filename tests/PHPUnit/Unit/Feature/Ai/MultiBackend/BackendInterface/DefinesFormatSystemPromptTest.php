<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\BackendInterface;

use Noem\State\Feature\Ai\Backend\BackendInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: BackendInterface defines formatSystemPrompt(string) method returning string
 *
 * Intent: Establishes system prompt formatting contract, enabling provider-specific
 * prompt template application
 */
#[Group('ai'), Group('backend-interface')]
class DefinesFormatSystemPromptTest extends TestCase
{
    #[Test]
    public function definesFormatSystemPromptMethod(): void
    {
        $reflection = new ReflectionClass(BackendInterface::class);

        $this->assertTrue($reflection->hasMethod('formatSystemPrompt'));

        $method = $reflection->getMethod('formatSystemPrompt');
        $this->assertTrue($method->isPublic());

        // Verify method signature
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('prompt', $parameters[0]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType->getName());
    }
}
