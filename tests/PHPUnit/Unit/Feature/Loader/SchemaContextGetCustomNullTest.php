<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Nette\Schema\Expect;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: SchemaContext getCustomSchema returns null for unknown name
 */
#[Group('loader')]
#[Group('schema-context')]
class SchemaContextGetCustomNullTest extends TestCase
{
    public function testGetCustomSchemaReturnsNullForUnknownName(): void
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

        $result = $context->getCustomSchema('nonExistentType');

        $this->assertNull(
            $result,
            'getCustomSchema should return null for unknown schema names'
        );
    }

    public function testGetCustomSchemaReturnsNullWhenNoCustomSchemasAdded(): void
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

        $result = $context->getCustomSchema('anyName');

        $this->assertNull($result);
    }
}
