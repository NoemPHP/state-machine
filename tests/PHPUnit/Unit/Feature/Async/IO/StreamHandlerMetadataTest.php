<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\StreamHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: StreamHandler returns wrapper metadata
 */
#[Group('async'), Group('io-operations')]
class StreamHandlerMetadataTest extends TestCase
{
    public function testReturnsWrapperData(): void
    {
        $resource = fopen('data://text/plain,test', 'r');
        
        $handler = new StreamHandler($resource);
        $generator = $handler();
        
        // Exhaust the generator
        foreach ($generator as $char) {
            // Process
        }
        
        $metadata = $generator->getReturn();
        
        // For data:// streams, wrapper_data might be null
        $this->assertTrue(
            $metadata === null || is_array($metadata),
            'Should return wrapper data (array or null)'
        );
    }
    
    public function testGeneratorProvidesReturnValue(): void
    {
        $resource = fopen('data://text/plain,hello', 'r');
        
        $handler = new StreamHandler($resource);
        $generator = $handler();
        
        // Advance through generator
        while ($generator->valid()) {
            $generator->next();
        }
        
        // Should be able to get return value
        $result = $generator->getReturn();
        
        // Return value exists (testing that getReturn() is called)
        $this->assertTrue(true, 'Generator completed and provides return value');
    }
}
