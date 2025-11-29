<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Nette\Schema\Expect;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: SchemaContext addCustomSchema stores custom type by name
 */
#[Group('loader')]
#[Group('schema-context')]
class SchemaContextAddCustomTest extends TestCase
{
    public function testAddCustomSchemaStoresCustomTypeByName(): void
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
        
        $customSchema = Expect::string();
        $context->addCustomSchema('myCustomType', $customSchema);
        
        $retrieved = $context->getCustomSchema('myCustomType');
        
        $this->assertSame(
            $customSchema,
            $retrieved,
            'addCustomSchema should store the custom schema by name'
        );
    }
    
    public function testAddCustomSchemaAllowsMultipleTypes(): void
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
        
        $schema1 = Expect::string();
        $schema2 = Expect::int();
        $schema3 = Expect::bool();
        
        $context->addCustomSchema('type1', $schema1);
        $context->addCustomSchema('type2', $schema2);
        $context->addCustomSchema('type3', $schema3);
        
        $this->assertSame($schema1, $context->getCustomSchema('type1'));
        $this->assertSame($schema2, $context->getCustomSchema('type2'));
        $this->assertSame($schema3, $context->getCustomSchema('type3'));
    }
}
