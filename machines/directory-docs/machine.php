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

require __DIR__ . '/../../vendor/autoload.php';
$yaml = file_get_contents(__DIR__ . '/machine.yml');
$helpers = [
    'php' => new PhpEvalHelper(),
];

$region = new RegionBuilder()->enableFeatures(
    new RegionLoader()->withYamlSupport($yaml, $helpers),
    new ExtendedState(),
    new TemplateFeature(),
    new AiFeature(),
    new AsyncFeature(),
    new OrthogonalRegions(),
    new JsonSchemaFeature(),
)->build();
while (!$region->isFinal()) {
    $region->trigger(
        (object)[
            'workingDir' => __DIR__ . '/../../src',
            'template' => file_get_contents(__DIR__ . '/template.md'),
        ]
    );
}
