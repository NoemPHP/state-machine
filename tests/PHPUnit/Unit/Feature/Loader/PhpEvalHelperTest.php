<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: PhpEvalHelper evaluates PHP code and returns result
 */
#[Group('loader')]
#[Group('yaml-helpers')]
class PhpEvalHelperTest extends TestCase
{
    public function testEvaluatesPhpCodeAndReturnsResult(): void
    {
        $helper = new PhpEvalHelper();
        
        $result = $helper('return 1 + 1');
        
        $this->assertEquals(2, $result);
    }
    
    public function testEvaluatesComplexExpressions(): void
    {
        $helper = new PhpEvalHelper();
        
        $result = $helper('return "Hello " . "World"');
        
        $this->assertEquals("Hello World", $result);
    }
}
