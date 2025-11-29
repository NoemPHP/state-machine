<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Fetch;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Fetch performs HTTP request as generator
 */
#[Group('async'), Group('io-operations')]
class FetchTest extends TestCase
{
    public function testCreatesGenerator(): void
    {
        $fetch = new Fetch('https://example.com');
        $generator = $fetch();
        
        $this->assertInstanceOf(\Generator::class, $generator, 'Fetch should return a generator');
    }
    
    public function testGeneratorYields(): void
    {
        // Using a data URL to avoid actual HTTP request
        $fetch = new Fetch('data://text/plain,Hello World');
        $generator = $fetch();
        
        $generator->valid();  // Start the generator
        $this->assertTrue($generator->valid(), 'Generator should yield values');
    }
    
    public function testConstructsWithUrl(): void
    {
        $fetch = new Fetch('https://example.com/test');
        $this->assertInstanceOf(Fetch::class, $fetch);
    }
    
    public function testConstructsWithMethod(): void
    {
        $fetch = new Fetch('https://example.com', 'POST');
        $this->assertInstanceOf(Fetch::class, $fetch);
    }
    
    public function testConstructsWithHeaders(): void
    {
        $fetch = new Fetch('https://example.com', 'GET', ['Accept' => 'application/json']);
        $this->assertInstanceOf(Fetch::class, $fetch);
    }
    
    public function testConstructsWithBody(): void
    {
        $fetch = new Fetch('https://example.com', 'POST', [], '{"key": "value"}');
        $this->assertInstanceOf(Fetch::class, $fetch);
    }
}
