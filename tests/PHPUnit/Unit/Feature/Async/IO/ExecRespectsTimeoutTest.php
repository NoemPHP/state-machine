<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Exec;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Exec respects timeout parameter
 */
#[Group('async'), Group('io-operations')]
class ExecRespectsTimeoutTest extends TestCase
{
    public function testTerminatesCommandOnTimeout(): void
    {
        // Sleep for 10 seconds but timeout after 1 second
        $exec = new Exec('sleep 10', 1);
        $generator = $exec();
        
        $startTime = microtime(true);
        
        // Exhaust the generator
        foreach ($generator as $chunk) {
            // Process output
        }
        
        $duration = microtime(true) - $startTime;
        $exitCode = $generator->getReturn();
        
        // Should timeout and not take the full 10 seconds
        $this->assertLessThan(5, $duration, 'Should timeout before command completes');
        $this->assertSame(1, $exitCode, 'Should return exit code 1 when timeout occurs');
    }
    
    public function testAllowsCompletionBeforeTimeout(): void
    {
        // Quick command with generous timeout
        $exec = new Exec('echo "fast"', 10);
        $generator = $exec();
        
        $output = '';
        foreach ($generator as $chunk) {
            $output .= $chunk;
        }
        
        $exitCode = $generator->getReturn();
        
        $this->assertStringContainsString('fast', $output);
        $this->assertSame(0, $exitCode, 'Should complete successfully before timeout');
    }
}
