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
class SummonAbsolutePathTest extends TestCase
{
    #[Test]
    public function summonAcceptsAbsoluteUnixPath(): void
    {
        // Create test file with absolute Unix path
        $testYaml = sys_get_temp_dir() . '/test-absolute.yml';
        file_put_contents($testYaml, "states:\n  - name: test\n    initial: true\n    final: true\n");

        try {
            $loaded = false;

            $region = (new RegionBuilder())
                ->enableFeatures(new RegionLoader(), new ExtendedState())
                ->setStates('idle', 'done')
                ->markInitial('idle')
                ->markFinal('done')
                ->onEnter('idle', function (object $t) use ($testYaml, &$loaded) {
                    // Use absolute path starting with /
                    $this->summon($testYaml);
                    $loaded = true;
                    return 'done';
                })
                ->build();

            $region->trigger((object)[]);

            $this->assertTrue($loaded, 'summon() should accept absolute Unix path');
        } finally {
            if (file_exists($testYaml)) {
                unlink($testYaml);
            }
        }
    }

    #[Test]
    public function summonAcceptsWindowsAbsolutePath(): void
    {
        // This test validates the path resolution logic, not actual file loading on Windows
        // We'll use reflection to test the isAbsolutePath method directly

        $loader = new RegionLoader();
        $reflection = new \ReflectionClass($loader);
        $method = $reflection->getMethod('isAbsolutePath');
        $method->setAccessible(true);

        // Unix absolute path
        $this->assertTrue(
            $method->invoke($loader, '/var/www/test.yml'),
            'Should recognize Unix absolute path'
        );

        // Windows absolute paths
        $this->assertTrue(
            $method->invoke($loader, 'C:\\machines\\test.yml'),
            'Should recognize Windows absolute path with backslash'
        );

        $this->assertTrue(
            $method->invoke($loader, 'C:/machines/test.yml'),
            'Should recognize Windows absolute path with forward slash'
        );

        // Relative paths
        $this->assertFalse(
            $method->invoke($loader, 'machines/test.yml'),
            'Should recognize relative path'
        );
    }
}
