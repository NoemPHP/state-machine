<?php

declare(strict_types=1);

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\Helper\ContainerGetHelper;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\Feature\Template\TemplateFeature;
use Noem\State\RegionBuilder;

require __DIR__.'/../../vendor/autoload.php';

class ServerConnection
{


    public readonly string $method;

    public readonly string $uri;

    public readonly string $protocol;

    public function __construct(public readonly mixed $client, public readonly string $request)
    {
        // Parse HTTP request
        $lines = explode("\r\n", $request);
        $requestLine = $lines[0];
        $parts = explode(' ', $requestLine);

        if (count($parts) < 3) {
            return;
        }
        $this->method = $parts[0];
        $this->uri = $parts[1];
        $this->protocol = $parts[2];
    }
}

;

$yaml = file_get_contents(__DIR__.'/machine.yml');
$helpers = [
    'php' => new PhpEvalHelper(),
    'get' => new ContainerGetHelper(include_once __DIR__.'/src/container.php'),
];

$region = new RegionBuilder()->enableFeatures(
    new RegionLoader(),
    new ExtendedState(),
    new TemplateFeature(),
    new AiFeature(),
    new AsyncFeature(),
    new OrthogonalRegions(),
    new JsonSchemaFeature(),
)->build([
    'loader' => [
        'yaml' => $yaml,
        'yamlHelpers' => $helpers,
    ],
]);
while (!$region->isFinal()) {
    $region->trigger(
        (object)[

        ]
    );
}
