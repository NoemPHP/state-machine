<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Integration\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Container services are accessible in state callbacks
 */
#[Group('loader'), Group('holon'), Group('integration')]
class ContainerAccessTest extends TestCase
{
    public function testServicesAccessibleInCallbacks(): void
    {
        // Arrange - Container provides callback closures at build time
        // language=yaml
        $yaml = <<<YAML
machine:
  container:
    services:
      onEnterCallback:
        factory: !php |
          return fn() => function(\$t) {
              \$t->configData = ['name' => 'Test Machine', 'version' => '1.0'];
          };
states:
  - name: active
    initial: true
    onEnter: 
      - run: !get "onEnterCallback"
  - name: done
    final: true
  - name: active
    transitions:
      - target: done
YAML;

        // Act
        $trigger = new \stdClass();
        $region = Holon::fromYaml($yaml);
        $region->trigger($trigger);

        // Assert - callback from container was resolved at build time and executed
        $this->assertEquals('done', $region->currentState());
        $this->assertIsArray($trigger->configData);
        $this->assertEquals('Test Machine', $trigger->configData['name']);
        $this->assertEquals('1.0', $trigger->configData['version']);
    }

    public function testFactoryServicesWork(): void
    {
        // Arrange - Factory creates timestamp value at build time
        // language=yaml
        $yaml = <<<YAML
machine:
  container:
    services:
      buildTimestamp:
        factory: !php "return fn() => time();"
      actionCallback:
        factory: !php |
          return function(\$container) {
              \$timestamp = \$container->get('buildTimestamp');
              return function(\$t) use (\$timestamp) {
                  \$t->timestamp = \$timestamp;
              };
          };
states:
  - name: ready
    initial: true
    action:
      - run: !get "actionCallback"
  - name: complete
    final: true
  - name: ready
    transitions:
      - target: complete
YAML;

        // Act
        $trigger = new \stdClass();
        $region = Holon::fromYaml($yaml);
        $beforeTrigger = time();
        $region->trigger($trigger);
        $afterTrigger = time();

        // Assert - timestamp was captured at build time, not runtime
        $this->assertIsInt($trigger->timestamp);
        $this->assertLessThanOrEqual($beforeTrigger, $trigger->timestamp);
        $this->assertGreaterThanOrEqual($beforeTrigger - 2, $trigger->timestamp); // Allow 2s margin
    }

    public function testClassServicesInstantiate(): void
    {
        // Arrange - Class instantiated at build time, used to create callback
        // language=yaml
        $yaml = <<<YAML
machine:
  container:
    services:
      datetime:
        class: DateTime
        arguments: ["2024-01-01"]
      callbackWithDate:
        factory: !php |
          return function(\$container) {
              \$date = \$container->get('datetime');
              return function(\$t) use (\$date) {
                  \$t->dateObject = \$date;
                  \$t->dateString = \$date->format('Y-m-d');
              };
          };
states:
  - name: initialized
    initial: true
    onEnter: 
      - run: !get "callbackWithDate"
  - name: processed
    final: true
  - name: initialized
    transitions:
      - target: processed
YAML;

        // Act
        $trigger = new \stdClass();
        $region = Holon::fromYaml($yaml);
        $region->trigger($trigger);

        // Assert - class was instantiated at build time and used in callback
        $this->assertInstanceOf(\DateTime::class, $trigger->dateObject);
        $this->assertEquals('2024-01-01', $trigger->dateString);
    }

    public function testMultipleServicesCoexist(): void
    {
        // Arrange - Multiple services resolved at build time via !get helper
        // language=yaml
        $yaml = <<<YAML
machine:
  container:
    services:
      service_a:
        value: "A"
      service_b:
        value: "B"
      service_c:
        factory: !php "return fn() => 'C';"
      actionCallback:
        factory: !php |
          return function(\$container) {
              return function(\$t) use (\$container) {
                  \$t->services = [
                      \$container->get('service_a'),
                      \$container->get('service_b'),
                      \$container->get('service_c')
                  ];
              };
          };
states:
  - name: operational
    initial: true
    action:
      - run: !get "actionCallback"
  - name: verified
    final: true
  - name: operational
    transitions:
      - target: verified
YAML;

        // Act
        $trigger = new \stdClass();
        $region = Holon::fromYaml($yaml);
        $region->trigger($trigger);

        // Assert - all services were resolved and accessible
        $this->assertIsArray($trigger->services);
        $this->assertCount(3, $trigger->services);
        $this->assertEquals(['A', 'B', 'C'], $trigger->services);
    }

    public function testServiceDependencyInjection(): void
    {
        // Arrange - Repository service depends on database service at build time
        // language=yaml
        $yaml = <<<YAML
machine:
  container:
    services:
      database:
        value: "db_connection"
      repository:
        factory: !php "return fn(\$c) => ['db' => \$c->get('database'), 'type' => 'repository'];"
      actionWithRepository:
        factory: !php |
          return function(\$container) {
              \$repo = \$container->get('repository');
              return function(\$t) use (\$repo) {
                  \$t->repo = \$repo;
              };
          };
states:
  - name: connected
    initial: true
    onEnter: 
      - run: !get "actionWithRepository"
  - name: active
    final: true
  - name: connected
    transitions:
      - target: active
YAML;

        // Act
        $trigger = new \stdClass();
        $region = Holon::fromYaml($yaml);
        $region->trigger($trigger);

        // Assert - repository was built with database dependency at build time
        $this->assertIsArray($trigger->repo);
        $this->assertEquals('db_connection', $trigger->repo['db']);
        $this->assertEquals('repository', $trigger->repo['type']);
    }

    public function testServicesAvailableDuringExecution(): void
    {
        // Arrange - Container provides configuration at build time
        // language=yaml
        $yaml = <<<YAML
machine:
  container:
    services:
      maxCount:
        value: 3
      actionCallback:
        factory: !php |
          return function(\$container) {
              \$max = \$container->get('maxCount');
              return function(\$t) use (\$max) {
                  \$t->maxReached = (\$t->currentCount ?? 0) >= \$max;
                  if (\$t->maxReached) {
                      \$t->result = 'completed';
                  }
              };
          };
  eventLoop:
    autoRun: true
    trigger: !php |
      \$trigger = null;
      return function(\$iteration, \$region, \$container) use (&\$trigger) {
          if (\$trigger === null) {
              \$trigger = new stdClass();
          }
          \$trigger->currentCount = \$iteration + 1;
          return \$trigger;
      };
states:
  - name: counting
    initial: true
    run: !service "actionCallback"
  - name: done
    final: true
  - name: counting
    transitions:
      - target: done
        guard: !php "return fn(\$t) => \$t->maxReached ?? false;"
YAML;

        // Act - autoRun executes with container-configured behavior
        $result = Holon::fromYaml($yaml);

        // Assert - execution completed using container-provided configuration
        $this->assertIsObject($result);
        $this->assertEquals('completed', $result->result);
        // Machine reached done state after 3 iterations (maxCount from container)
    }
}
