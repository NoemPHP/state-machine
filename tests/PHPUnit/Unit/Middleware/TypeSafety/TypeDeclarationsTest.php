<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\TypeSafety;

use Noem\State\Middleware\Chain;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: Proper PHP 8.4+ type declarations throughout
 */
#[Group('middleware')]
#[Group('type-safety')]
class TypeDeclarationsTest extends TestCase
{
    public function testChainHasStrictTypes(): void
    {
        $reflection = new ReflectionClass(Chain::class);
        $fileName = $reflection->getFileName();

        $this->assertNotFalse($fileName);

        $content = file_get_contents($fileName);

        $this->assertStringContainsString('declare(strict_types=1);', $content);
    }

    public function testMeshHasStrictTypes(): void
    {
        $reflection = new ReflectionClass(Mesh::class);
        $fileName = $reflection->getFileName();

        $this->assertNotFalse($fileName);

        $content = file_get_contents($fileName);

        $this->assertStringContainsString('declare(strict_types=1);', $content);
    }

    public function testChainMailHasStrictTypes(): void
    {
        $reflection = new ReflectionClass(ChainMail::class);
        $fileName = $reflection->getFileName();

        $this->assertNotFalse($fileName);

        $content = file_get_contents($fileName);

        $this->assertStringContainsString('declare(strict_types=1);', $content);
    }

    public function testChainCallMethodHasReturnType(): void
    {
        $reflection = new ReflectionClass(Chain::class);
        $method = $reflection->getMethod('call');

        $this->assertTrue($method->hasReturnType());
    }

    public function testChainLinkMethodHasReturnType(): void
    {
        $reflection = new ReflectionClass(Chain::class);
        $method = $reflection->getMethod('link');

        $this->assertTrue($method->hasReturnType());
    }

    public function testMeshOffsetGetHasReturnType(): void
    {
        $reflection = new ReflectionClass(Mesh::class);
        $method = $reflection->getMethod('offsetGet');

        $this->assertTrue($method->hasReturnType());
    }
}
