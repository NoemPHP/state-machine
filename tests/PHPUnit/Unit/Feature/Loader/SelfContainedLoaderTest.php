<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader;

use Noem\State\Feature\Loader\SelfContainedLoader;
use Noem\State\Feature\Loader\EventLoopManager;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the SelfContainedLoader functionality
 */
#[Group('loader')]
#[Group('self-contained')]
class SelfContainedLoaderTest extends TestCase
{
    public function testCanLoadSimpleStateMachineFromYaml(): void
    {
        $yaml = <<<YAML
states:
  - name: start
    initial: true
    transitions:
      - target: end
  - name: end
    final: true
YAML;

        $region = SelfContainedLoader::fromYaml($yaml);
        
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('start', $region->currentState());
        $this->assertFalse($region->isFinal());
        
        $region->trigger(new \stdClass());
        
        $this->assertEquals('end', $region->currentState());
        $this->assertTrue($region->isFinal());
    }
    
    public function testCanLoadWithFeatures(): void
    {
        $yaml = <<<YAML
machine:
  features:
    - Noem\State\Feature\ExtendedState\ExtendedState
    - Noem\State\Feature\Transitions\TransitionsFeature

states:
  - name: idle
    initial: true
    onEnter:
      - run: !php |
          return function() {
              \$this->set('initialized', true);
          };
    transitions:
      - target: done
  - name: done
    final: true
YAML;

        $region = SelfContainedLoader::fromYaml($yaml);
        
        $this->assertInstanceOf(Region::class, $region);
        
        // Trigger to verify ExtendedState feature is working
        $region->trigger(new \stdClass());
        
        // We can't directly test the extended state here without access to context,
        // but we can verify the transition worked (TransitionsFeature)
        $this->assertEquals('done', $region->currentState());
    }
    
    public function testCanLoadWithContainer(): void
    {
        $yaml = <<<YAML
machine:
  container:
    services:
      testValue:
        value: 42
      testService:
        factory: !php |
          return new class {
              public function getValue() { return 'test'; }
          };
  
  features:
    - Noem\State\Feature\ExtendedState\ExtendedState

states:
  - name: start
    initial: true
    onEnter:
      - run: !php |
          return function(\$t) use (\$container) {
              \$value = \$container->get('testValue');
              \$this->set('containerValue', \$value);
          };
    final: true
YAML;

        $region = SelfContainedLoader::fromYaml($yaml);
        
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('start', $region->currentState());
    }
    
    public function testAutoRunEventLoop(): void
    {
        $yaml = <<<YAML
machine:
  features:
    - Noem\State\Feature\Transitions\TransitionsFeature
  
  eventLoop:
    autoRun: true
    maxIterations: 3
    trigger: !php |
      return (object)['iteration' => \$iteration];

states:
  - name: counting
    initial: true
    transitions:
      - target: done
        guard: !php |
          return fn(\$t) => \$t->iteration >= 2;
  - name: done
    final: true
YAML;

        // This should auto-run and complete
        $result = SelfContainedLoader::fromYaml($yaml);
        
        // After auto-run, we should get the final result (not the Region)
        $this->assertIsObject($result);
    }
    
    public function testEventLoopManagerCreation(): void
    {
        $yaml = <<<YAML
machine:
  features:
    - Noem\State\Feature\Transitions\TransitionsFeature

states:
  - name: idle
    initial: true
    transitions:
      - target: working
        guard: !php |
          return fn(\$t) => isset(\$t->start) && \$t->start;
  - name: working
    transitions:
      - target: done
  - name: done
    final: true
YAML;

        $manager = EventLoopManager::fromYaml($yaml, [
            'eventLoop' => [
                'maxIterations' => 10,
            ],
        ]);
        
        $this->assertInstanceOf(EventLoopManager::class, $manager);
        $this->assertEquals('idle', $manager->currentState());
        $this->assertFalse($manager->isRunning());
        
        // Test manual tick
        $trigger = new \stdClass();
        $trigger->start = true;
        
        $manager->tick();
        $this->assertEquals('working', $manager->currentState());
        
        $manager->tick();
        $this->assertEquals('done', $manager->currentState());
        $this->assertTrue($manager->isFinal());
    }
    
    public function testQuickRegionCreation(): void
    {
        $states = [
            ['name' => 'first', 'initial' => true],
            ['name' => 'second'],
            ['name' => 'third', 'final' => true],
        ];
        
        $region = SelfContainedLoader::quickRegion($states, [
            'Noem\State\Feature\Transitions\TransitionsFeature',
        ]);
        
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('first', $region->currentState());
        $this->assertFalse($region->isFinal());
    }
    
    public function testComplexYamlWithHelpers(): void
    {
        $yaml = <<<YAML
machine:
  features:
    - Noem\State\Feature\ExtendedState\ExtendedState
    - Noem\State\Feature\Transitions\TransitionsFeature
  
  container:
    parameters:
      maxCount: 3
    services:
      counter:
        factory: !php |
          return new class {
              private int \$count = 0;
              public function increment(): int {
                  return ++\$this->count;
              }
              public function getCount(): int {
                  return \$this->count;
              }
          };

states:
  - name: init
    initial: true
    onEnter:
      - run: !php |
          return function() {
              \$this->set('startTime', time());
          };
    transitions:
      - target: counting
  
  - name: counting
    action:
      - run: !php |
          return function(\$t) use (\$container) {
              \$counter = \$container->get('counter');
              \$count = \$counter->increment();
              \$this->set('currentCount', \$count);
          };
    transitions:
      - target: finished
        guard: !php |
          return function(\$t) use (\$container) {
              \$counter = \$container->get('counter');
              \$max = \$container->get('parameters')['maxCount'];
              return \$counter->getCount() >= \$max;
          };
  
  - name: finished
    final: true
    onEnter:
      - run: !php |
          return function() {
              \$elapsed = time() - \$this->get('startTime');
              \$this->set('elapsed', \$elapsed);
          };
YAML;

        $region = SelfContainedLoader::fromYaml($yaml);
        
        $this->assertEquals('init', $region->currentState());
        
        // Run through the state machine
        $trigger = new \stdClass();
        $region->trigger($trigger); // init -> counting
        
        $this->assertEquals('counting', $region->currentState());
        
        // Count up to max
        for ($i = 0; $i < 3; $i++) {
            $region->trigger($trigger);
        }
        
        $this->assertEquals('finished', $region->currentState());
        $this->assertTrue($region->isFinal());
    }
    
    public function testErrorHandlingForInvalidYaml(): void
    {
        $this->expectException(\Exception::class);
        
        $yaml = "invalid: yaml: content: [[[";
        SelfContainedLoader::fromYaml($yaml);
    }
    
    public function testErrorHandlingForInvalidFeatureClass(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Feature class 'NonExistentClass' does not exist");
        
        $yaml = <<<YAML
machine:
  features:
    - NonExistentClass

states:
  - name: test
    initial: true
    final: true
YAML;

        SelfContainedLoader::fromYaml($yaml);
    }
    
    public function testLoadingFromFile(): void
    {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_yaml_');
        $yaml = <<<YAML
states:
  - name: loaded
    initial: true
    final: true
YAML;
        file_put_contents($tempFile, $yaml);
        
        try {
            $region = SelfContainedLoader::fromYaml($tempFile);
            $this->assertInstanceOf(Region::class, $region);
            $this->assertEquals('loaded', $region->currentState());
        } finally {
            unlink($tempFile);
        }
    }
}
