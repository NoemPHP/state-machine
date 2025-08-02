<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Template;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Call;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Template\TemplateFeature;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Test;

class TemplateFeatureTest extends RegionBuilderTestCase
{

    public function setUp(): void
    {
        parent::setUp();
        $this->builder->enableFeatures(
            new TemplateFeature(),
            new AsyncFeature(),
            new ExtendedState()
        );
    }

    #[Test] public function usageInCallback()
    {

        $region = $this->builder
            ->setStates('one', 'two')
            ->onAction('one', function (object $t) {
                $template = $this->template('rofl');
                assert($template instanceof \Generator);
                while ($template->valid()) {
                    $template->next();
                    yield;
                }
                $result = $template->getReturn();

                $this->set('result', $result);
            })
            ->build();
        $trigger = new \stdClass();
        $region->trigger($trigger);
        $region->trigger($trigger);
        $region->trigger($trigger);
        $region->trigger($trigger);
        $region->trigger($trigger);
        $this->assertRegionContext($region, 'result', 'rofl');
    }

    #[Test] public function usageInTask()
    {
        $region = $this->builder
            ->setStates('one', 'two')
            ->onAction('one', function (object $t) {
                $this->set('result', yield Call::call($this->template('rofl')));
            })
            ->build();
        $trigger = new \stdClass();
        $region->trigger($trigger);
        $region->trigger($trigger);
        $region->trigger($trigger);
        $region->trigger($trigger);
        $region->trigger($trigger);
        $this->assertRegionContext($region, 'result', 'rofl');
    }
}
