<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Includes;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Includes\LoadFileParams;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoadFileParams stores BuildParams context as readonly property
 */
#[Group('includes')]
#[Group('params')]
class ParamsStoresBuildContextTest extends TestCase
{
    public function testStoresBuildContext(): void
    {
        $filePath = '/path/to/file.yaml';
        $buildContext = $this->createMock(BuildParams::class);

        $params = new LoadFileParams($filePath, $buildContext);

        $this->assertSame($buildContext, $params->buildContext);
    }
}
