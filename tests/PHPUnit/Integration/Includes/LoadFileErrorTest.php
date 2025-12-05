<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Includes;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Includes\Chains\LoadFile;
use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Includes\LoadFileParams;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoadFile chain throws RuntimeException for unreadable files
 */
#[Group('includes')]
#[Group('file-loading')]
class LoadFileErrorTest extends TestCase
{
    public function testThrowsRuntimeExceptionForUnreadableFile(): void
    {
        $chainMail = new ChainMail();
        $feature = new IncludesFeature();
        $feature($chainMail);

        $loadFile = $chainMail->get(LoadFile::class);
        $buildContext = $this->createMock(BuildParams::class);
        $nonExistentPath = '/path/to/nonexistent/file.yaml';
        $params = new LoadFileParams($nonExistentPath, $buildContext);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($nonExistentPath);

        $loadFile->call($params);
    }
}
