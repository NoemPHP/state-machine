<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\ConvertYaml;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ConvertYaml throws RuntimeException for undefined helper
 */
#[Group('loader')]
#[Group('yaml-conversion')]
class UndefinedHelperErrorTest extends TestCase
{
    public function testThrowsRuntimeExceptionForUndefinedHelper(): void
    {
        $yaml = <<<YAML
        value: !unknown "test"
        YAML;
        
        $converter = new ConvertYaml();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Undefined helper 'unknown'");
        
        $converter->fromString($yaml, []);
    }
}
