<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AddResolver;
use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Priority;
use Noem\State\Feature\Async\Resolvers;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

final class AddResolverAsyncConfigTest extends TestCase
{
    public function testAddResolverAcceptsAsyncConfig(): void
    {
        $config = new AsyncConfig(priority: Priority::HIGH);
        $resolver = function () {
            yield;
            return 'value';
        };

        $addResolver = new AddResolver('myResolver', $resolver, $config);

        $this->assertInstanceOf(AddResolver::class, $addResolver);
    }

    public function testAddResolverCreatesRecordWithAsyncConfig(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $config = new AsyncConfig(priority: Priority::HIGH, singleton: true);
        $resolver = function () {
            yield;
            return 'value';
        };

        $builder->addBuildStep(new AddResolver('myResolver', $resolver, $config));

        $region = $builder
            ->setStates('active')
            ->build();

        // Access resolvers service to verify record was created
        $reflection = new \ReflectionClass($builder);
        $chainMailProperty = $reflection->getProperty('chainMail');
        $chainMailProperty->setAccessible(true);
        $chainMail = $chainMailProperty->getValue($builder);

        $resolversService = $chainMail->get(Resolvers::class);
        $resolverRecords = $resolversService->getResolversForRegion($region);

        $this->assertCount(1, $resolverRecords, 'Should have 1 resolver');

        $record = $resolverRecords['myResolver'];
        $this->assertNotNull($record, 'Record should exist');
        $this->assertNotNull($record->asyncConfig, 'AsyncConfig should be stored');
        $this->assertSame(Priority::HIGH, $record->asyncConfig->priority);
        $this->assertTrue($record->asyncConfig->singleton);
    }

    public function testAddResolverWithoutAsyncConfigCreatesRecordWithNull(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $resolver = function () {
            yield;
            return 'value';
        };

        // No AsyncConfig provided
        $builder->addBuildStep(new AddResolver('myResolver', $resolver));

        $region = $builder
            ->setStates('active')
            ->build();

        // Access resolvers service
        $reflection = new \ReflectionClass($builder);
        $chainMailProperty = $reflection->getProperty('chainMail');
        $chainMailProperty->setAccessible(true);
        $chainMail = $chainMailProperty->getValue($builder);

        $resolversService = $chainMail->get(Resolvers::class);
        $resolverRecords = $resolversService->getResolversForRegion($region);

        $this->assertCount(1, $resolverRecords);

        $record = $resolverRecords['myResolver'];
        $this->assertNotNull($record, 'Record should exist');
        $this->assertNull($record->asyncConfig, 'AsyncConfig should be null for backward compatibility');
    }

    public function testMultipleResolversWithDifferentConfigs(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $highPriorityResolver = function () {
            yield;
            return 'high';
        };

        $lowPriorityResolver = function () {
            yield;
            return 'low';
        };

        $defaultResolver = function () {
            yield;
            return 'default';
        };

        $builder->addBuildStep(new AddResolver(
            'highPriority',
            $highPriorityResolver,
            new AsyncConfig(priority: Priority::HIGH)
        ));

        $builder->addBuildStep(new AddResolver(
            'lowPriority',
            $lowPriorityResolver,
            new AsyncConfig(priority: Priority::LOW, timeout: 5.0)
        ));

        $builder->addBuildStep(new AddResolver('default', $defaultResolver));

        $region = $builder
            ->setStates('active')
            ->build();

        // Access resolvers service
        $reflection = new \ReflectionClass($builder);
        $chainMailProperty = $reflection->getProperty('chainMail');
        $chainMailProperty->setAccessible(true);
        $chainMail = $chainMailProperty->getValue($builder);

        $resolversService = $chainMail->get(Resolvers::class);
        $resolverRecords = $resolversService->getResolversForRegion($region);

        $this->assertCount(3, $resolverRecords, 'Should have 3 resolvers');

        // Verify high priority
        $highRecord = $resolverRecords['highPriority'];
        $this->assertNotNull($highRecord, 'High priority record should exist');
        $this->assertEquals('highPriority', $highRecord->key);
        $this->assertNotNull($highRecord->asyncConfig);
        $this->assertSame(Priority::HIGH, $highRecord->asyncConfig->priority);

        // Verify low priority with timeout
        $lowRecord = $resolverRecords['lowPriority'];
        $this->assertNotNull($lowRecord, 'Low priority record should exist');
        $this->assertEquals('lowPriority', $lowRecord->key);
        $this->assertNotNull($lowRecord->asyncConfig);
        $this->assertSame(Priority::LOW, $lowRecord->asyncConfig->priority);
        $this->assertEquals(5.0, $lowRecord->asyncConfig->timeout);

        // Verify default (no config)
        $defaultRecord = $resolverRecords['default'];
        $this->assertNotNull($defaultRecord, 'Default record should exist');
        $this->assertEquals('default', $defaultRecord->key);
        $this->assertNull($defaultRecord->asyncConfig);
    }
}
