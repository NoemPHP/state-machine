<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: PhpEvalHelper converts PHP errors to ErrorException
 */
#[Group('loader')]
#[Group('yaml-helpers')]
class PhpEvalErrorHandlingTest extends TestCase
{
    public function testConvertsPhpErrorsToErrorException(): void
    {
        $helper = new PhpEvalHelper();
        
        $this->expectException(\RuntimeException::class);
        
        // This should trigger a PHP error (undefined variable) which gets converted to ErrorException
        // and then wrapped in RuntimeException
        $helper('return $undefinedVariable');
    }
}
