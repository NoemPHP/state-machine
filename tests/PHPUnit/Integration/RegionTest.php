<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration;

use Noem\State\Chains\BuildRegion;
use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Chains\Params\BuildParams;
use Noem\State\Connection;
use Noem\State\Feature\ExtendedState\Bound;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\NamedEvents\Event;
use Noem\State\Feature\NamedEvents\Name;
use Noem\State\Feature\NamedEvents\NamedEvents;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Middleware\ChainException;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

class RegionTest extends RegionBuilderTestCase
{

    /**
     * @return void
     */
    #[Test]
    public function basicTransition()
    {
        $enterSpy = \Mockery::spy(fn() => true);
        $exitSpy = \Mockery::spy(fn() => true);
        $guardSpy = \Mockery::spy(fn() => true);
        $r = new RegionBuilder();

        $r = $r
            ->setStates('one', 'two')
            ->onExit('one', fn(object $t) => $exitSpy())
            ->onEnter('two', fn(object $t) => $enterSpy())
            ->markInitial('one')
            ->addBuildStep(
                new AddTransition('one', 'two', fn(object $t): bool => $guardSpy()),
            )
            ->build();

        $r->trigger((object)['foo' => 1]);

        $this->assertTrue($r->isInState('two'));
        $guardSpy->shouldHaveBeenCalled()->once();
        $exitSpy->shouldHaveBeenCalled()->once();
        $enterSpy->shouldHaveBeenCalled()->once();
    }

    /**
     * @return void
     * @throws ChainException
     */
    #[Test]
    public function basicSubRegion()
    {
        $handler = \Mockery::spy(fn() => true);
        $r = new RegionBuilder();

        $r
            ->enableFeatures(
                new OrthogonalRegions()
            )
            ->setStates('one', 'two')
            ->markInitial('one')
            ->addBuildStep(
                new AddTransition('one', 'two', fn(object $t): bool => true)
            )
            ->connect(
                $r
                    ->newInstance()
                    ->setStates('foo', 'bar')
                    ->onAction('foo', function (object $t) use ($handler) {
                        $handler();
                    })->markFinal('foo')->build(),
                Connection::DYNAMIC
                | Connection::RECEIVE_EVENTS
                | Connection::RECEIVE_ACTIONS
                | Connection::RECEIVE_META,
                fn(Connection $c) => $c->local->currentState() === 'one'
            );
        $region = $r->build();
        $region->trigger((object)['foo' => 1]);

        $handler->shouldHaveBeenCalled()->once();
        $this->assertTrue($region->isInState('two'));
    }

    /**
     * @return void
     */
    #[Test]
    public function getStateContext()
    {
        $this->markTestSkipped('The concept of state context is under review');
        $test = null;
        $r = new RegionBuilder();
        $r->setStates('one', 'two')
            ->connect(
                $r->newInstance()
                    ->setStates('foo', 'bar')
                    ->inherits(['key'])
                    ->onAction('foo', function (object $t) use (&$test) {
                        assert($this instanceof Bound);
                        $test = $this->key;
                    })
                    ->setStateContext('foo', [
                        'key' => 'value',
                    ])->build(),
                Connection::DYNAMIC
                | Connection::RECEIVE_EVENTS
                | Connection::RECEIVE_ACTIONS
                | Connection::RECEIVE_META,
                fn(Connection $c) => $c->local->currentState() === 'one'
            );

        $r->build()->trigger((object)['foo' => 1]);
        $this->assertSame($test, 'value');
    }

    /**
     * @return void
     * @throws ChainException
     */
    #[Test]
    public function nestedRegionContext()
    {
        $this->builder->enableFeatures(new ExtendedState());
        $remoteChildRegion = $this->builder->newInstance()
            ->setStates('child1', 'child2')
            ->onAction('child1', function (object $t) use (&$test) {
                assert($this instanceof Bound);
                $test = $this->get('key');
            })
            ->build();

        $this->builder
            ->setStates('parent1', 'parent2')
            ->setMetaData(
                [
                    'key' => 'value',
                ],
                ContextMetaType::get()
            )
            ->connect(
                $remoteChildRegion,
                Connection::DYNAMIC
                | Connection::RECEIVE_EVENTS
                | Connection::RECEIVE_ACTIONS
                | Connection::RECEIVE_META,
                fn(Connection $c) => $c->local->currentState() === 'parent1'
            );

        $this->builder->build()->trigger((object)['foo' => 1]);
        $this->assertRegionContext(
            $remoteChildRegion,
            'key',
            'value',
            'Subregion should have received the "key" => "value" context data from its parent'
        );
    }

    /**
     * Assert that a child region receives the same metadata as the parent
     *
     * @throws ChainException
     */
    #[Test]
    public function getInheritedRegionContext(): void
    {
        $this->builder->enableFeatures(new ExtendedState());

        $remoteRegion = ($this->builder->newInstance())
            ->setStates('foo', 'bar')
            ->onAction('foo', function (object $t) use (&$test) {
                assert($this instanceof Bound);
                $test = $this->get('key');
            })->build();

        $this->builder->setStates('one', 'two')
            ->connect(
                $remoteRegion,
                Connection::DYNAMIC
                | Connection::RECEIVE_EVENTS
                | Connection::RECEIVE_ACTIONS
                | Connection::RECEIVE_META,
                fn(Connection $c) => $c->local->currentState() === 'one'
            )
            ->setMetaData([
                'key' => 'value',
            ], ContextMetaType::get());

        $this->builder->build()->trigger((object)['foo' => 1]);
        $this->assertRegionContext($remoteRegion, 'key', 'value');
    }

    /**
     * @return void
     * @throws ChainException
     */
    #[Test]
    public function setInheritedRegionContext()
    {
        $this->builder->enableFeatures(new ExtendedState());
        $subRegionBuilder = $this->builder->newInstance()
            ->setStates('foo', 'bar')
            ->onAction('foo', function (object $t) use (&$test) {
                assert($this instanceof Bound);
                $this->set('key', 'newValue');
            });
        $subRegion = $subRegionBuilder->build();

        $this->builder->setStates('one', 'two')
            ->connect(
                $subRegion,
                Connection::DYNAMIC
                | Connection::RECEIVE_EVENTS
                | Connection::RECEIVE_ACTIONS
                | Connection::RECEIVE_META,
                fn(Connection $c) => $c->local->currentState() === 'one'
            )
            ->setMetaData([
                'key' => 'value',
            ], ContextMetaType::get());
        $region = $this->builder->build();
        $region->trigger((object)['foo' => 1]);

        $this->assertRegionContext($region, 'key', 'newValue');
        $this->assertRegionContext($subRegion, 'key', 'newValue');
    }

    /**
     * @return void
     * @throws ChainException
     */
    #[Test]
    public function mutateInheritedRegionContext()
    {
        $this->builder->enableFeatures(new ExtendedState());

        $subRegionBuilder = $this->builder->newInstance()
            ->setStates('foo', 'bar')
            ->onAction('foo', function (object $t) use (&$test) {
                assert($this instanceof Bound);
                $key = $this->get('key');
                $this->set('key', $key . ' world');
            });
        $subRegion = $subRegionBuilder->build();
        $this->builder->setStates('one', 'two')
            ->connect(
                $subRegion,
                Connection::DYNAMIC
                | Connection::RECEIVE_EVENTS
                | Connection::RECEIVE_ACTIONS
                | Connection::RECEIVE_META,
                fn(Connection $c) => $c->local->currentState() === 'one'
            )
            ->setMetaData(
                [
                    'key' => 'hello',
                ],
                ContextMetaType::get()
            );
        $region = $this->builder->build();
        $region->trigger((object)['foo' => 1]);
        $this->assertRegionContext($region, 'key', 'hello world');
    }

    /**
     * @return void
     */
    #[Test]
    public function simpleMiddleware()
    {
        $r = new RegionBuilder();
        $r->setStates('one', 'two', 'three')
            ->addBuildStep(new AddTransition('one', 'two'))
            ->chainMail->use(function (EnhanceRegionBuilder $builderMiddleware) {
                $builderMiddleware->link(function (BuildParams $params, \Closure $next) {
                    $builder = $next($params);
                    assert($builder instanceof RegionBuilder);
                    $builder->addBuildStep(new AddTransition('two', 'three', fn(object $t): bool => true));

                    return $next($params);
                });
            });

        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $region->trigger((object)['foo' => 1]);
        $this->assertTrue($region->isInState('three'));
    }

    /**
     * @return void
     * @throws ChainException
     */
    #[Test]
    #[TestDox('It correctly sets up a nested logging middleware')]
    public function nestedLoggingMiddleware()
    {
        $this->markTestSkipped('Needs rewrite');
        /**
         * TODO This is generally an important integration test,
         * but builder middleware is a pretty low-level, almost internal feature now.
         * Logging should be a "Feature" under the chainmail paradigm
         * So we need to rethink this test. Maybe we should have a "Feature" for logging
         * that can be added to the builder, and then we can test that feature separately.
         *
         * Alternatively, we could see if a nested builder middleware makes sense and how it could be tested
         */
        $logs = [];

        $middleware = function (BuildRegion $builderMiddleware) use (&$logs) {
            $builderMiddleware->link(function (RegionBuilder $builder, \Closure $next) use (&$logs) {
                $builder->eachState(function (string $s) use ($builder, &$logs) {
                    $builder->onEnter($s, function (object $trigger) use ($s, &$logs) {
                        $logs[] = "ENTER: $s";
                    });
                    $builder->onExit($s, function (object $trigger) use ($s, &$logs) {
                        $logs[] = "EXIT: $s";
                    });
                });

                return $next($builder);
            });
        };

        $r = new RegionBuilder();
        $r->chainMail->use($middleware);
        $subRegion = $r->newInstance()
            ->setStates('2_foo', '2_bar')
            ->addBuildStep(new AddTransition('2_foo', '2_bar'));
        $subRegion = $subRegion->build();

        $r->setStates('1_one', '1_two')
            ->addBuildStep(new AddTransition('1_one', '1_two'))
            ->connect(
                $subRegion,
                Connection::DYNAMIC
                | Connection::RECEIVE_EVENTS
                | Connection::RECEIVE_ACTIONS
                | Connection::RECEIVE_META,
                fn(Connection $c) => $c->local->currentState() === '1_two'
            );

        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $region->trigger((object)['foo' => 1]);

        //var_dump($logs);

        $this->assertSame(5, count($logs));
        $this->assertTrue($region->isInState('1_two'));
    }

    /**
     * @return void
     */
    #[Test]
    public function nestedStateName()
    {
        $fqsn = '';
        $r = new RegionBuilder();
        $subRegion = $r
            ->enableFeatures(new ExtendedState())
            ->newInstance()
            ->setStates('two')
            ->onAction('two', function (object $t) use (&$fqsn) {
                assert($this instanceof Bound);
                $fqsn = (string)$this;
            });

        $r->setStates('one')
            ->connect(
                $subRegion->build(),
                Connection::DYNAMIC
                | Connection::RECEIVE_EVENTS
                | Connection::RECEIVE_ACTIONS
                | Connection::RECEIVE_META,
                fn(Connection $c) => $c->local->currentState() === 'one'
            );

        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $this->assertSame('one/two', $fqsn);
    }

    /**
     * @return void
     * @throws ChainException
     */
    #[Test]
    public function eventChaining()
    {
        $this->markTestSkipped(
            "I am worried about the infinite-loop-potential of dispatching immediately. This is disabled for now until I find a roadblock that forces me to have this functionality"
        );
        $r = new RegionBuilder();
        $r->setStates('one', 'two', 'three')
            ->addBuildStep(new AddTransition('one', 'two'))
            ->addBuildStep(new AddTransition('two', 'three'))
            ->onEnter('two', function (object $trigger) {
                /** @noinspection PhpUndefinedMethodInspection */
                $this->dispatch((object)['hello' => 'world']);
            });
        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $this->assertTrue($region->isInState('three'), "Region should be in state 'three'");
    }

    /**
     * @return void
     */
    #[Test]
    public function namedEvents()
    {
        $guardSpy = \Mockery::spy(fn() => true);

        $r = new RegionBuilder();
        $namedEventsFeature = new NamedEvents();
        $r->enableFeatures($namedEventsFeature)->setStates('one', 'two', 'three')
            ->addBuildStep(new AddTransition('one', 'two', fn(#[Name('hello-world')] Event $t): bool => $guardSpy()))
            ->addBuildStep(new AddTransition('two', 'three', fn(#[Name('ignore-me')] Event $t): bool => $guardSpy()));
        $region = $r->build();
        $region->trigger((object)['foo' => 1]);
        $this->assertFalse($region->isInState('two'), "Region should ignore non-matching event'");

        $event = new class implements Event {

            public function name(): string
            {
                return 'hello-world';
            }
        };

        $region->trigger($event);
        $region->trigger($event);

        $this->assertTrue($region->isInState('two'), "Region should be in state 'two'");
    }
}
