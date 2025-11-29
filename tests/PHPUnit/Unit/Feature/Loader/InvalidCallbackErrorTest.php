<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray throws RuntimeException for invalid run callback
 */
#[Group('loader')]
#[Group('guard-and-callback-creation')]
class InvalidCallbackErrorTest extends TestCase
{
    public function testThrowsRuntimeExceptionForInvalidRunCallback(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        $definition = [
            'run' => 'not_a_callable',
        ];
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid "run" callback');
        
        $processor->createStateCallback($definition);
    }
}
