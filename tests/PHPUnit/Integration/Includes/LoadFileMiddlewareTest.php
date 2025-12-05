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
 * Acceptance Criterion: LoadFile chain executes middleware in LIFO order
 */
#[Group('includes')]
#[Group('file-loading')]
class LoadFileMiddlewareTest extends TestCase
{
    public function testExecutesMiddlewareInLifoOrder(): void
    {
        $chainMail = new ChainMail();
        $feature = new IncludesFeature();
        $feature($chainMail);

        $executionOrder = [];
        $loadFile = $chainMail->get(LoadFile::class);

        // Link middleware to track execution order
        $loadFile->link(function (LoadFileParams $params, callable $next) use (&$executionOrder) {
            $executionOrder[] = 'middleware1-pre';
            $result = $next($params);
            $executionOrder[] = 'middleware1-post';
            return $result;
        });

        $loadFile->link(function (LoadFileParams $params, callable $next) use (&$executionOrder) {
            $executionOrder[] = 'middleware2-pre';
            $result = $next($params);
            $executionOrder[] = 'middleware2-post';
            return $result;
        });

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');

        try {
            $buildContext = $this->createMock(BuildParams::class);
            $params = new LoadFileParams($tempFile, $buildContext);

            $loadFile->call($params);

            // Middleware executes in registration order (FIFO):
            // First registered middleware wraps later middleware
            $this->assertSame([
                'middleware1-pre',
                'middleware2-pre',
                'middleware2-post',
                'middleware1-post',
            ], $executionOrder);
        } finally {
            unlink($tempFile);
        }
    }
}
