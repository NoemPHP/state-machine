<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: runEventLoop returns last trigger object
 */
#[Group('loader'), Group('holon'), Group('holon-event-loop')]
class ResultReturnTest extends TestCase
{
    public function testReturnsLastTriggerObject(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
states:
  - name: start
    initial: true
    run: !php "return fn(\$t) => \$t->iteration = 'first';"
  - name: end
    final: true
    run: !php "return fn(\$t) => \$t->iteration = 'last';"
  - name: start
    transitions:
      - target: end
YAML;

        // Act
        $result = Holon::fromYaml($yaml);

        // Assert - should return the trigger object from the final iteration
        $this->assertIsObject($result);
        $this->assertEquals('last', $result->iteration);
    }

    public function testReturnsTriggerObjectWhenNoActions(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
states:
  - name: start
    initial: true
  - name: end
    final: true
  - name: start
    transitions:
      - target: end
YAML;

        // Act
        $result = Holon::fromYaml($yaml);

        // Assert - should return the default trigger object
        $this->assertIsObject($result);
    }

    public function testReturnsTriggerFromFinalIteration(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
states:
  - name: s1
    initial: true
    run: !php "return fn(\$t) => \$t->state = 's1';"
  - name: s2
    run: !php "return fn(\$t) => \$t->state = 's2';"
  - name: s3
    final: true
    run: !php "return fn(\$t) => \$t->state = 's3';"
  - name: s1
    transitions:
      - target: s2
  - name: s2
    transitions:
      - target: s3
YAML;

        // Act
        $result = Holon::fromYaml($yaml);

        // Assert - should return trigger from final state
        $this->assertIsObject($result);
        $this->assertEquals('s3', $result->state);
    }

    public function testTriggerObjectCanHoldComplexData(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
states:
  - name: start
    initial: true
  - name: end
    final: true
    run: !php "return fn(\$t) => \$t->data = ['status' => 'completed', 'code' => 200];"
  - name: start
    transitions:
      - target: end
YAML;

        // Act
        $result = Holon::fromYaml($yaml);

        // Assert - trigger object can hold complex data
        $this->assertIsObject($result);
        $this->assertIsArray($result->data);
        $this->assertEquals('completed', $result->data['status']);
        $this->assertEquals(200, $result->data['code']);
    }
}
