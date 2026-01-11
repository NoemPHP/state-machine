<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
class YamlInteractionsDiscoverableTest extends TestCase
{
    public function testYamlRegisteredInteractionsDiscoverableViaEnumerateInteractionsAbility(): void
    {
        $yamlContent = <<<YAML
states:
  - name: ready
    interactions:
      - id: proceed_confirm
        type: confirm
        question: Proceed?
  - name: processing
    interactions:
      - id: select_action
        type: select
        question: Choose action
        options:
          continue: Continue
          abort: Abort
YAML;

        $tempFile = tempnam(sys_get_temp_dir(), 'interaction_test_') . '.yaml';
        file_put_contents($tempFile, $yamlContent);

        try {
            $yaml = file_get_contents($tempFile);
            $yaml = "machine:\n  features:\n    - class: Noem\\State\\Feature\\Subscription\\SubscriptionFeature\n    - class: Noem\\State\\Feature\\Message\\MessageFeature\n    - class: Noem\\State\\Feature\\ExtendedState\\ExtendedState\n    - class: Noem\\State\\Feature\\Interaction\\InteractionFeature\n    - class: Noem\\State\\Feature\\Abilities\\AbilitiesFeature\n    - class: Noem\\State\\Feature\\Interaction\\InteractionRegistryFeature\n" . $yaml;

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
            $ability = $abilityRegistry->get('enumerate-interactions');
            $result = ($ability->handler)(null);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('interactions', $result);
            $this->assertCount(2, $result['interactions']);

            $ids = array_column($result['interactions'], 'id');
            $this->assertContains('proceed_confirm', $ids);
            $this->assertContains('select_action', $ids);
        } finally {
            unlink($tempFile);
        }
    }
}
