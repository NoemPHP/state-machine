<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Exec;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Exec yields command output incrementally
 */
#[Group('async'), Group('io-operations')]
class ExecYieldsOutputTest extends TestCase
{
    public function testYieldsOutputIncrementally(): void
    {
        // Using printf with newlines to get multiple output chunks
        $exec = new Exec('printf "line1\nline2\nline3\n"');
        $generator = $exec();
        
        $chunks = [];
        foreach ($generator as $chunk) {
            $chunks[] = $chunk;
        }
        
        $this->assertGreaterThan(0, count($chunks), 'Should yield output incrementally');
        
        $fullOutput = implode('', $chunks);
        $this->assertStringContainsString('line1', $fullOutput);
        $this->assertStringContainsString('line2', $fullOutput);
        $this->assertStringContainsString('line3', $fullOutput);
    }
    
    public function testYieldsMultipleChunks(): void
    {
        $exec = new Exec('echo "first" && echo "second"');
        $generator = $exec();
        
        $output = '';
        $yieldCount = 0;
        foreach ($generator as $chunk) {
            $output .= $chunk;
            $yieldCount++;
        }
        
        $this->assertGreaterThan(0, $yieldCount, 'Should yield at least one chunk');
        $this->assertStringContainsString('first', $output);
        $this->assertStringContainsString('second', $output);
    }
}
