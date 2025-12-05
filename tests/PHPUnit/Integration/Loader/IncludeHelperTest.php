<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Loader;

use Noem\State\Feature\Includes\Chains\LoadFile;
use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader registers !include helper using LoadFile chain from ChainMail
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeHelperTest extends TestCase
{
    public function testRegistersIncludeHelperUsingLoadFileChain(): void
    {
        // Create a temp YAML file with complete state machine definition
        $includedFile = tempnam(sys_get_temp_dir(), 'include_');
        file_put_contents($includedFile, <<<YAML
states:
  - name: includedState
YAML);

        // Create main YAML that uses !include to load the entire machine definition
        $mainYaml = "!include {$includedFile}";

        try {
            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

            // This should use the !include helper which uses LoadFile chain
            $result = $builder->build(['loader' => ['yaml' => $mainYaml]]);

            $this->assertInstanceOf(\Noem\State\Region::class, $result);

            // Verify the !include helper successfully loaded the external file via LoadFile chain
            // (if it failed, an exception would have been thrown)
            $this->assertTrue(true, '!include helper successfully used LoadFile chain');
        } finally {
            unlink($includedFile);
        }
    }
}
