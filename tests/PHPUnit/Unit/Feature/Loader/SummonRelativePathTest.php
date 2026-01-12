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
class SummonRelativePathTest extends TestCase
{
    #[Test]
    public function summonResolvesRelativePathAgainstGetcwd(): void
    {
        // Create test file in a subdirectory of current working directory
        $subdir = getcwd() . '/test-machines';
        if (!is_dir($subdir)) {
            mkdir($subdir, 0777, true);
        }

        $testYaml = $subdir . '/test-relative.yml';
        file_put_contents($testYaml, "states:\n  - name: test\n    initial: true\n    final: true\n");

        try {
            $loaded = false;

            $region = (new RegionBuilder())
                ->enableFeatures(new RegionLoader(), new ExtendedState())
                ->setStates('idle', 'done')
                ->markInitial('idle')
                ->markFinal('done')
                ->onEnter('idle', function (object $t) use (&$loaded) {
                    // Use relative path - should resolve to getcwd() . '/test-machines/test-relative.yml'
                    $this->summon('test-machines/test-relative.yml');
                    $loaded = true;
                    return 'done';
                })
                ->build();

            $region->trigger((object)[]);

            $this->assertTrue($loaded, 'summon() should resolve relative path against getcwd()');
        } finally {
            if (file_exists($testYaml)) {
                unlink($testYaml);
            }
            if (is_dir($subdir)) {
                rmdir($subdir);
            }
        }
    }

    #[Test]
    public function summonHandlesParentDirectoryInRelativePath(): void
    {
        // Create test file one level up, then down into a subdirectory
        // This tests the path resolution with .. segments
        $currentDir = getcwd();
        $testFile = $currentDir . '/test-parent-dir.yml';
        file_put_contents($testFile, "states:\n  - name: test\n    initial: true\n    final: true\n");

        try {
            $loaded = false;

            $region = (new RegionBuilder())
                ->enableFeatures(new RegionLoader(), new ExtendedState())
                ->setStates('idle', 'done')
                ->markInitial('idle')
                ->markFinal('done')
                ->onEnter('idle', function (object $t) use (&$loaded) {
                    // Relative path that will be resolved against getcwd()
                    $this->summon('test-parent-dir.yml');
                    $loaded = true;
                    return 'done';
                })
                ->build();

            $region->trigger((object)[]);

            $this->assertTrue($loaded, 'summon() should handle relative paths');
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }
}
