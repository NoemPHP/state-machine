<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\AsyncFeature\Unit;

use Noem\State\Feature\Async\IO\Fetch;
use PHPUnit\Framework\TestCase;

class FetchTest extends TestCase
{
    public function testGet()
    {
        $this->markTestSkipped();
        $client = new Fetch('https://jsonplaceholder.typicode.com/posts/1');
        $generator = $client();
        $response = iterator_to_array($generator);
    }
}
