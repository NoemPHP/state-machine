<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Resolvers;

use Noem\State\Feature\Async\ResolverRecord;
use Noem\State\Feature\Async\Resolvers;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Resolvers checks resolver existence for region and key
 */
#[Group('async'), Group('resolver-registry')]
class ChecksExistenceTest extends TestCase
{
    public function testChecksResolverExistence(): void
    {
        $resolvers = new Resolvers();

        $region = (new RegionBuilder())->setStates('idle')->build();
        $callback = fn() => 'value';

        $this->assertFalse($resolvers->hasResolver($region, 'property'));

        $resolvers->addResolver(new ResolverRecord($region, 'property', $callback));

        $this->assertTrue($resolvers->hasResolver($region, 'property'));
    }

    public function testReturnsFalseForNonExistentResolver(): void
    {
        $resolvers = new Resolvers();

        $region = (new RegionBuilder())->setStates('idle')->build();

        $this->assertFalse($resolvers->hasResolver($region, 'nonexistent'));
    }

    public function testChecksExistencePerRegion(): void
    {
        $resolvers = new Resolvers();

        $region1 = (new RegionBuilder())->setStates('idle')->build();
        $region2 = (new RegionBuilder())->setStates('active')->build();

        $resolvers->addResolver(new ResolverRecord($region1, 'property', fn() => 'value'));

        $this->assertTrue($resolvers->hasResolver($region1, 'property'));
        $this->assertFalse(
            $resolvers->hasResolver($region2, 'property'),
            'hasResolver should check region-specific existence'
        );
    }

    public function testChecksExistencePerKey(): void
    {
        $resolvers = new Resolvers();

        $region = (new RegionBuilder())->setStates('idle')->build();

        $resolvers->addResolver(new ResolverRecord($region, 'prop1', fn() => 'value1'));
        $resolvers->addResolver(new ResolverRecord($region, 'prop2', fn() => 'value2'));

        $this->assertTrue($resolvers->hasResolver($region, 'prop1'));
        $this->assertTrue($resolvers->hasResolver($region, 'prop2'));
        $this->assertFalse($resolvers->hasResolver($region, 'prop3'));
    }

    public function testExistenceCheckBeforeRetrieval(): void
    {
        $resolvers = new Resolvers();

        $region = (new RegionBuilder())->setStates('idle')->build();

        // Pattern: check before retrieve to avoid null handling
        if ($resolvers->hasResolver($region, 'property')) {
            $resolver = $resolvers->getResolver($region, 'property');
            $this->fail('Should not retrieve non-existent resolver');
        }

        $resolvers->addResolver(new ResolverRecord($region, 'property', fn() => 'value'));

        if ($resolvers->hasResolver($region, 'property')) {
            $resolver = $resolvers->getResolver($region, 'property');
            $this->assertNotNull($resolver);
        } else {
            $this->fail('hasResolver should return true after adding resolver');
        }
    }
}
