<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
class SchemaRequiresTypeTest extends TestCase
{
    public function testSchemaValidatesTypeAsRequiredStringInInteractionDefinitions(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('type');

        $yamlContent = <<<YAML
states:
  - name: ready
    interactions:
      - id: proceed_confirm
        question: Proceed?
YAML;

        $tempFile = tempnam(sys_get_temp_dir(), 'interaction_test_') . '.yaml';
        file_put_contents($tempFile, $yamlContent);

        try {
            $builder = new \Noem\State\RegionBuilder();
            $builder->enableFeatures(
                new \Noem\State\Feature\Subscription\SubscriptionFeature(),
                new \Noem\State\Feature\Message\MessageFeature(),
                new \Noem\State\Feature\ExtendedState\ExtendedState(),
                new \Noem\State\Feature\Interaction\InteractionFeature(),
                new \Noem\State\Feature\Abilities\AbilitiesFeature(),
                new InteractionRegistryFeature(),
                new \Noem\State\Feature\Loader\RegionLoader()
            );
            $region = $builder->build(['loader' => ['yaml' => file_get_contents($tempFile)]]);
        } finally {
            unlink($tempFile);
        }
    }
}
