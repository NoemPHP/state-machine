<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: Schema chain has unlimited restarts
 */
#[Group('loader')]
#[Group('loader-chains')]
class SchemaUnlimitedRestartsTest extends TestCase
{
    public function testSchemaChainHasUnlimitedRestarts(): void
    {
        $schema = new Schema();
        
        $reflection = new ReflectionClass($schema);
        $property = $reflection->getProperty('maxRestarts');
        
        $value = $property->getValue($schema);
        
        $this->assertSame(
            -1,
            $value,
            'Schema chain should have unlimited restarts (maxRestarts = -1)'
        );
    }
}
