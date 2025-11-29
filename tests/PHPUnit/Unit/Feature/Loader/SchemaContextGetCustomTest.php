<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: SchemaContext getCustomSchema retrieves custom type by name
 */
#[Group('loader')]
#[Group('schema-context')]
class SchemaContextGetCustomTest extends TestCase
{
    public function testGetCustomSchemaRetrievesCustomTypeByName(): void
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
        
        $customSchema = Expect::array();
        $context->addCustomSchema('testType', $customSchema);
        
        $retrieved = $context->getCustomSchema('testType');
        
        $this->assertInstanceOf(
            Schema::class,
            $retrieved,
            'getCustomSchema should return a Schema instance'
        );
        
        $this->assertSame(
            $customSchema,
            $retrieved,
            'getCustomSchema should retrieve the exact schema stored'
        );
    }
}
