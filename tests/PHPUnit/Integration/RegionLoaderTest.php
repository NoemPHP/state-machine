<?php

namespace Noem\State\Test\Integration;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\RegionLoader;

class RegionLoaderTest extends MockeryTestCase
{

    /**
     * @test
     * @return void
     */
    public function fromYam()
    {
        // language=yaml
        $yaml = <<<YAML
states:
  - one
  - two
  - three
initial: one
final: three
regions:
  two:
    - states:
      - one_one
      - one_two
      - one_three

YAML;
        $loader = RegionLoader::fromYaml($yaml);
        $loader->build();
    }
}
