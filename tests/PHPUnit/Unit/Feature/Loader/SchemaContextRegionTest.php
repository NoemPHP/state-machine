<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: SchemaContext stores region structure
 */
#[Group('loader')]
#[Group('schema-context')]
class SchemaContextRegionTest extends TestCase
{
    public function testSchemaContextStoresRegionStructure(): void
    {
        $callbackSchema = Expect::anyOf(Expect::string(), Expect::array());
        $actionSchema = Expect::structure([]);
        $stateSchema = Expect::structure([]);
        $regionSchema = Expect::structure([]);

        $context = new SchemaContext(
            $callbackSchema,
            $actionSchema,
            $stateSchema,
            $regionSchema
        );

        $this->assertInstanceOf(
            Structure::class,
            $context->region,
            'SchemaContext should store region structure'
        );

        $this->assertSame(
            $regionSchema,
            $context->region,
            'SchemaContext should store the exact region structure instance'
        );
    }
}
