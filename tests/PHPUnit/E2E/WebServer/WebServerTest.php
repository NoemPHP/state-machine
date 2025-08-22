<?php

declare(strict_types=1);

namespace Noem\State\Test\E2E\WebServer;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\Feature\Template\TemplateFeature;
use Noem\State\Test\E2E\ApplicationTestCase;
use PHPUnit\Framework\Attributes\Test;

class WebServerTest extends ApplicationTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->builder->enableFeatures(
            new RegionLoader(),
            new ExtendedState(),
            new TemplateFeature(),
            new AiFeature(),
            new AsyncFeature(),
            new OrthogonalRegions(),
            new JsonSchemaFeature(),
        );
    }

    #[Test]
    public function concurrency()
    {
        $result = $this->execute();
    }

    public function yaml(): string
    {
        return file_get_contents(dirname(__DIR__, 4) . '/machines/webserver/machine.yml');
    }

    public function container(): iterable
    {
        return include dirname(__DIR__, 4) . '/machines/webserver/src/container.php';
    }

    public function trigger(): object
    {
        $trigger = new \stdClass();

        return $trigger;
    }
}
