<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Exec;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Exec returns exit code after completion
 */
#[Group('async'), Group('io-operations')]
class ExecReturnsExitCodeTest extends TestCase
{
    public function testReturnsZeroExitCodeOnSuccess(): void
    {
        $exec = new Exec('echo "test"');
        $generator = $exec();
        
        // Exhaust the generator
        foreach ($generator as $chunk) {
            // Process output
        }
        
        $exitCode = $generator->getReturn();
        
        $this->assertSame(0, $exitCode, 'Should return exit code 0 for successful command');
    }
    
    public function testReturnsNonZeroExitCodeOnFailure(): void
    {
        $exec = new Exec('exit 1');
        $generator = $exec();
        
        // Exhaust the generator
        foreach ($generator as $chunk) {
            // Process output
        }
        
        $exitCode = $generator->getReturn();
        
        $this->assertSame(1, $exitCode, 'Should return exit code 1 for failed command');
    }
    
    public function testReturnsExitCodeAfterCompletion(): void
    {
        $exec = new Exec('true');
        $generator = $exec();
        
        // Advance through generator
        while ($generator->valid()) {
            $generator->next();
        }
        
        $exitCode = $generator->getReturn();
        
        $this->assertIsInt($exitCode, 'Should return integer exit code');
    }
}
