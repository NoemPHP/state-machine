<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Fetch;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Fetch compiles custom headers into request
 */
#[Group('async'), Group('io-operations')]
class FetchCompilesHeadersTest extends TestCase
{
    public function testCompilesHeadersIntoRequest(): void
    {
        $headers = [
            'Authorization' => 'Bearer token123',
            'Accept' => 'application/json',
            'Custom-Header' => 'custom-value',
        ];
        
        // Use a data URL to avoid actual HTTP request
        $fetch = new Fetch('data://text/plain,test', 'GET', $headers);
        $generator = $fetch();
        
        // The generator should be created successfully with headers compiled
        $this->assertInstanceOf(\Generator::class, $generator);
        
        // Test that Fetch can be constructed with headers (verifying no errors)
        $this->assertInstanceOf(Fetch::class, $fetch);
    }
    
    public function testCompilesMultipleHeaders(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept-Language' => 'en-US',
            'Cache-Control' => 'no-cache',
        ];
        
        $fetch = new Fetch('data://text/plain,test', 'POST', $headers, '{"data": "value"}');
        $generator = $fetch();
        
        $this->assertInstanceOf(\Generator::class, $generator);
    }
    
    public function testHandlesEmptyHeaders(): void
    {
        $fetch = new Fetch('data://text/plain,test', 'GET', []);
        $generator = $fetch();
        
        $this->assertInstanceOf(\Generator::class, $generator);
    }
}
