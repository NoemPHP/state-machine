<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature;

use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\RegionBuilder;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class JsonSchemaTest extends RegionBuilderTestCase
{
    #[Test]
    public function happyPath()
    {
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
