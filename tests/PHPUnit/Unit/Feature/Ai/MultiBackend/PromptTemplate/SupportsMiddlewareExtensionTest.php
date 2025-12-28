<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend\PromptTemplate;

use Noem\State\Feature\Ai\Chains\PromptTemplate;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: PromptTemplate supports middleware extension via link() method
 *
 * Intent: Enables features to inject custom template resolution logic through chain middleware
 */
#[Group('ai'), Group('prompt-template-chain')]
class SupportsMiddlewareExtensionTest extends TestCase
{
    #[Test]
    public function supportsMiddlewareExtensionViaLink(): void
    {
        $promptTemplate = new PromptTemplate();

        $promptTemplate->registerTemplate('openai', 'completion', 'Original template');

        $called = false;
        $promptTemplate->link(function (array $context, callable $next) use (&$called) {
            $called = true;
            $result = $next($context);
            $result['template'] = 'Modified: ' . $result['template'];
            return $result;
        });

        $result = $promptTemplate('openai', 'completion');

        $this->assertTrue($called, 'Middleware should be called');
        $this->assertEquals('Modified: Original template', $result);
    }
}
