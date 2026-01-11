<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
class YamlStateAssociationTest extends TestCase
{
    public function testStateLevelYamlInteractionsAssociateWithCorrectStateProperty(): void
    {
        $yamlContent = <<<YAML
states:
  - name: ready
    interactions:
      - id: ready_confirm
        type: confirm
        question: Ready?
  - name: processing
    interactions:
      - id: processing_select
        type: select
        question: Choose
        options:
          a: Option A
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

            $abilityRegistry = $builder->chainMail->get(AbilityRegistry::class);
            $ability = $abilityRegistry->get('get-interactions-for-state');

            // Test ready state
            $readyResult = ($ability->handler)(['state' => 'ready']);
            $this->assertCount(1, $readyResult['interactions']);
            $this->assertSame('ready_confirm', $readyResult['interactions'][0]['id']);
            $this->assertSame('ready', $readyResult['interactions'][0]['state']);

            // Test processing state
            $processingResult = ($ability->handler)(['state' => 'processing']);
            $this->assertCount(1, $processingResult['interactions']);
            $this->assertSame('processing_select', $processingResult['interactions'][0]['id']);
            $this->assertSame('processing', $processingResult['interactions'][0]['state']);
        } finally {
            unlink($tempFile);
        }
    }
}
