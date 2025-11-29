<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray throws RuntimeException for invalid factory
 */
#[Group('loader')]
#[Group('guard-and-callback-creation')]
class InvalidFactoryErrorTest extends TestCase
{
    public function testThrowsRuntimeExceptionForInvalidFactory(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid factory');
        
        $processor->createFactoryCallback('not_a_callable');
    }
}
