<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Fetch;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Fetch supports different HTTP methods
 */
#[Group('async'), Group('io-operations')]
class FetchSupportsMethodsTest extends TestCase
{
    public function testSupportsGetMethod(): void
    {
        $fetch = new Fetch('data://text/plain,test', 'GET');
        $generator = $fetch();

        $this->assertInstanceOf(\Generator::class, $generator);
    }

    public function testSupportsPostMethod(): void
    {
        $fetch = new Fetch('data://text/plain,test', 'POST', [], '{"key": "value"}');
        $generator = $fetch();

        $this->assertInstanceOf(\Generator::class, $generator);
    }

    public function testSupportsPutMethod(): void
    {
        $fetch = new Fetch('data://text/plain,test', 'PUT', [], '{"key": "updated"}');
        $generator = $fetch();

        $this->assertInstanceOf(\Generator::class, $generator);
    }

    public function testSupportsDeleteMethod(): void
    {
        $fetch = new Fetch('data://text/plain,test', 'DELETE');
        $generator = $fetch();

        $this->assertInstanceOf(\Generator::class, $generator);
    }

    public function testSupportsPatchMethod(): void
    {
        $fetch = new Fetch('data://text/plain,test', 'PATCH', [], '{"key": "patched"}');
        $generator = $fetch();

        $this->assertInstanceOf(\Generator::class, $generator);
    }
}
