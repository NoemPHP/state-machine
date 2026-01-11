<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
class SchemaAcceptsConstraintsTest extends TestCase
{
    public function testSchemaValidatesConstraintsAsOptionalArrayInInteractionDefinitions(): void
    {
        $yamlContent = <<<YAML
states:
  - name: ready
    interactions:
      - id: prompt_name
        type: prompt
        question: Enter your name
        constraints:
          minLength: 3
          maxLength: 50
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

            $registry = $builder->chainMail->get(InteractionRegistry::class);
            $definition = $registry->get('prompt_name');

            $this->assertNotNull($definition);
            $this->assertSame(['minLength' => 3, 'maxLength' => 50], $definition->constraints);
        } finally {
            unlink($tempFile);
        }
    }
}
