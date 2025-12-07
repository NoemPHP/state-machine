<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: SchemaContext stores callback schema
 */
#[Group('loader')]
#[Group('schema-context')]
class SchemaContextCallbackTest extends TestCase
{
    public function testSchemaContextStoresCallbackSchema(): void
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
            Schema::class,
            $context->callback,
            'SchemaContext should store callback schema'
        );

        $this->assertSame(
            $callbackSchema,
            $context->callback,
            'SchemaContext should store the exact callback schema instance'
        );
    }
}
