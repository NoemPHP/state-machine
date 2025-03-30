<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\JsonSchema;

use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Test;

class JsonSchemaTest extends RegionBuilderTestCase
{
    #[Test]
    public function happyPath()
    {
        $this->markTestSkipped();
        $r = $this->builder
            ->enableFeatures(new JsonSchemaFeature())
            ->setStates('foo', 'bar', 'baz')
            ->setMetaData([
                'foo' => 'bar',
            ])
            ->build();
        $this->assertRegionContext($r, 'foo', 'bar',);
        //$this->expectException(\TypeError::class);
    }
}
