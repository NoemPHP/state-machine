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

    public function __construct(public readonly mixed $client)
    {
        $request_line = '';
        $buffer = '';
        $line_length = 0;

        // Read the request line
        while ($line_length < 1024 && ($line = fgets($client, 1024))) {
            $request_line .= $line;
            if (strpos($line, "\r\n") !== false) {
                break; // Stop after the first line
            }
        }

        // Extract the request line
        $request_line = explode(" ", $request_line, 3);
        if (count($request_line) < 3) {
            return false; // Invalid request line
        }

        $method = $request_line[0];
        $url = $request_line[1];
        $http_version = $request_line[2];

        // Extract the path from the URL
        $path = parse_url($url, PHP_URL_PATH);
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
