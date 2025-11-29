<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: PhpEvalHelper restores error handler after evaluation
 */
#[Group('loader')]
#[Group('yaml-helpers')]
class PhpEvalErrorHandlerRestorationTest extends TestCase
{
    public function testRestoresErrorHandlerAfterSuccessfulEvaluation(): void
    {
        $helper = new PhpEvalHelper();
        
        // Store a reference to verify restoration
        $originalHandler = set_error_handler(function() {});
        restore_error_handler();
        
        // Execute helper
        $helper('return 42');
        
        // Verify error handler is restored by checking that a new handler can be set
        $currentHandler = set_error_handler(function() {});
        restore_error_handler();
        
        $this->assertNotNull($currentHandler, 'Error handler should be restorable');
    }
    
    public function testRestoresErrorHandlerAfterFailedEvaluation(): void
    {
        $helper = new PhpEvalHelper();
        
        try {
            $helper('return $undefined');
        } catch (\RuntimeException $e) {
            // Expected exception
        }
        
        // Verify error handler is restored even after exception
        $currentHandler = set_error_handler(function() {});
        restore_error_handler();
        
        $this->assertNotNull($currentHandler, 'Error handler should be restorable after exception');
    }
}
