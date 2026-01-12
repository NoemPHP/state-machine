<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Loader;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('loader')]
class SummonAcceptsFilepathTest extends TestCase
{
    #[Test]
    public function summonRequiresStringFilepath(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('summon() requires a filepath string as first parameter');

        $region = (new RegionBuilder())
            ->enableFeatures(new RegionLoader(), new ExtendedState())
            ->setStates('idle')
            ->markInitial('idle')
            ->onEnter('idle', function (object $t) {
                // Call summon() with invalid parameter (not a string)
                $this->summon(123);
            })
            ->build();

        $region->trigger((object)[]);
    }

    #[Test]
    public function summonAcceptsStringFilepath(): void
    {
        $testYaml = sys_get_temp_dir() . '/test-filepath.yml';
        file_put_contents($testYaml, "states:\n  - name: test\n    initial: true\n    final: true\n");

        try {
            $success = false;

            $region = (new RegionBuilder())
                ->enableFeatures(new RegionLoader(), new ExtendedState())
                ->setStates('idle', 'done')
                ->markInitial('idle')
                ->markFinal('done')
                ->onEnter('idle', function (object $t) use ($testYaml, &$success) {
                    // Call summon() with valid string filepath
                    $this->summon($testYaml);
                    $success = true;
                    return 'done';
                })
                ->build();

            $region->trigger((object)[]);

            $this->assertTrue($success, 'summon() should accept string filepath without error');
        } finally {
            if (file_exists($testYaml)) {
                unlink($testYaml);
            }
        }
    }
}
