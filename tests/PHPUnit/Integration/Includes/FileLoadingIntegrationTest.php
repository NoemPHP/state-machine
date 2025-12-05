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
 * Acceptance Criterion: LoadFile chain successfully loads files through middleware stack
 */
#[Group('includes')]
#[Group('integration')]
class FileLoadingIntegrationTest extends TestCase
{
    public function testLoadsFilesSuccessfullyThroughMiddlewareStack(): void
    {
        // Create test file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $originalContent = "original content";
        file_put_contents($tempFile, $originalContent);

        try {
            $chainMail = new ChainMail();
            $feature = new IncludesFeature();
            $feature($chainMail);

            $loadFile = $chainMail->get(LoadFile::class);

            // Add middleware that transforms content
            $loadFile->link(function (LoadFileParams $params, callable $next) {
                $content = $next($params);
                return strtoupper($content); // Transform to uppercase
            });

            // Add middleware that adds prefix
            $loadFile->link(function (LoadFileParams $params, callable $next) {
                $content = $next($params);
                return "PREFIX: " . $content; // Add prefix
            });

            $buildContext = $this->createMock(BuildParams::class);
            $params = new LoadFileParams($tempFile, $buildContext);

            $result = $loadFile->call($params);

            // Verify middleware transformations applied in LIFO order
            // Last middleware (prefix) executes first, then uppercase
            $this->assertSame("PREFIX: ORIGINAL CONTENT", $result);
        } finally {
            unlink($tempFile);
        }
    }
}
