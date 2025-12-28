<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\BackendInterface;

use Noem\State\Feature\Ai\Backend\BackendInterface;
use Noem\State\Feature\Ai\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: BackendInterface defines createCompletionRequest(Request) method returning array
 *
 * Intent: Establishes completion request creation contract, ensuring all backends can transform
 * generic Request to provider-specific completion API format
 */
#[Group('ai'), Group('backend-interface')]
class DefinesCreateCompletionRequestTest extends TestCase
{
    #[Test]
    public function definesCreateCompletionRequestMethod(): void
    {
        $reflection = new ReflectionClass(BackendInterface::class);

        $this->assertTrue($reflection->hasMethod('createCompletionRequest'));

        $method = $reflection->getMethod('createCompletionRequest');
        $this->assertTrue($method->isPublic());

        // Verify method signature
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('request', $parameters[0]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }
}