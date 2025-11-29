<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: PhpEvalHelper includes code fragment in exception message
 */
#[Group('loader')]
#[Group('yaml-helpers')]
class PhpEvalErrorFragmentTest extends TestCase
{
    public function testIncludesCodeFragmentInExceptionMessage(): void
    {
        $helper = new PhpEvalHelper();
        
        $code = "return \$undefined";
        
        try {
            $helper($code);
            $this->fail('Expected RuntimeException to be thrown');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            
            // The fragment should include the code
            $this->assertStringContainsString('return $undefined', $message);
            
            // The fragment should include line numbers
            $this->assertStringContainsString('0 |', $message);
        }
    }
}
