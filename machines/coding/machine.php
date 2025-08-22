<?php

declare(strict_types=1);

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\Machine;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\Feature\Template\TemplateFeature;

require __DIR__.'/../../vendor/autoload.php';

return Machine::run(
    new class extends Machine {

        public function features(): iterable
        {
            return array_merge([
                new ExtendedState(),
                new TemplateFeature(),
                new AiFeature(),
                new AsyncFeature(),
                new OrthogonalRegions(),
                new JsonSchemaFeature(),
            ], parent::features());
        }

        public function trigger(): object
        {
            return new stdClass();
        }

        public function container(): array
        {
            return include_once __DIR__.'/container.php';
        }

        public function yaml(): string
        {
            return file_get_contents(__DIR__.'/coding.yml');
        }
    }
);
