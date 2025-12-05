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
 * Acceptance Criterion: RegionLoader registers !includeRelative helper using LoadFile chain from ChainMail
 */
#[Group('loader')]
#[Group('yaml-include-helpers')]
class IncludeRelativeHelperTest extends TestCase
{
    public function testRegistersIncludeRelativeHelperUsingLoadFileChain(): void
    {
        // Create temp file with valid state definition
        $includedFile = tempnam(sys_get_temp_dir(), 'includeRelative_');
        file_put_contents($includedFile, <<<YAML
- name: relativeState
YAML
        );

        try {
            // Main YAML uses !includeRelative to load states
            $mainYaml = "states: !includeRelative " . basename($includedFile);

            $builder = new RegionBuilder();
            $builder->enableFeatures(new IncludesFeature(), new RegionLoader());

            // This should use the !includeRelative helper which uses LoadFile chain
            $result = $builder->build(
                [
                    'loader' => [
                        'yaml' => $mainYaml,
                        'array' => [
                            'includes' => [
                                'basePath' => dirname($includedFile),
                            ],
                        ],
                    ]
                ]
            );

            $this->assertInstanceOf(\Noem\State\Region::class, $result);
        } finally {
            unlink($includedFile);
        }
    }
}
