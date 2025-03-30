<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\AsyncFeature\Unit;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\Feature\Async\Ornament;
use Noem\State\Feature\Async\OrnamentResolver;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OrnamentTest extends MockeryTestCase
{
    #[Test]
    public function simpleResolution()
    {
        $data = ['a' => 'hello'];
        $mesh = new Mesh($data);
        $resolvingMesh = new Ornament($mesh, 'b', fn(OrnamentResolver $r) => $r->resolve('my value'));
        $result = $mesh['b'];
        $this->assertSame($result, 'my value');
    }

    #[Test]
    public function withDependency()
    {
        $data = ['a' => 'hello'];
        $mesh = \Mockery::spy(new Mesh($data));
        $resolvingMesh = new Ornament($mesh, 'b', function (OrnamentResolver $r) {
            $a = $r->get('a');

            $r->resolve($a . ' world');
        });
        //$mesh->expects('offsetGet')->twice()->with('a');

        $mesh['b'];

        $result = $mesh['b'];
        $this->assertSame($result, 'hello world');
        $mesh['a'] = 'goodbye';
        $result = $mesh['b'];
        $this->assertSame($result, 'goodbye world');
    }
}
