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

// Conditionally include ServerConnection if not already defined (allows test mocking)
if (!class_exists('ServerConnection', false)) {
    require_once __DIR__.'/src/ServerConnection.php';
}

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
