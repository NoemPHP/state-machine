<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: SchemaContext stores action structure
 */
#[Group('loader')]
#[Group('schema-context')]
class SchemaContextActionTest extends TestCase
{
    public function testSchemaContextStoresActionStructure(): void
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
            $context->action,
            'SchemaContext should store action structure'
        );

        $this->assertSame(
            $actionSchema,
            $context->action,
            'SchemaContext should store the exact action structure instance'
        );
    }
}
