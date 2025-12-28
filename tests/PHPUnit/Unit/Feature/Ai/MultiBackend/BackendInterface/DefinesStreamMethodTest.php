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
 * Acceptance Criterion: BackendInterface defines stream(Request) method returning iterable
 *
 * Intent: Establishes streaming contract, ensuring all backends provide consistent
 * streaming interface for both completion and chat
 */
#[Group('ai'), Group('backend-interface')]
class DefinesStreamMethodTest extends TestCase
{
    #[Test]
    public function definesStreamMethod(): void
    {
        $reflection = new ReflectionClass(BackendInterface::class);

        $this->assertTrue($reflection->hasMethod('stream'));

        $method = $reflection->getMethod('stream');
        $this->assertTrue($method->isPublic());

        // Verify method signature
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('request', $parameters[0]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('iterable', $returnType->getName());
    }
}