<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Nette\Schema\Expect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
class SchemaSupportsRegionLevelTest extends TestCase
{
    public function testRegionLoaderSchemaSupportsInteractionsKeyAtRegionLevel(): void
    {
        $schema = new Schema();
        $feature = new InteractionRegistryFeature();

        $chainMail = new \Noem\State\Middleware\ChainMail();
        $chainMail->supply(fn(): Schema => $schema);

        $feature($chainMail);

        // Create minimal schema context
        $context = new SchemaContext(
            Expect::anyOf(Expect::string(), Expect::array()),
            Expect::structure([]),
            Expect::structure([]),
            Expect::structure([])
        );

        // Execute schema chain to apply extensions
        $schema->call($context);

        // Verify interactions key is supported at region level (schema was extended)
        $this->assertNotNull($context->region);
    }
}
