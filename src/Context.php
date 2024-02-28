<?php

namespace Noem\State;

class Context
{

    /**
     * @var iterable<Region>
     */
    private \SplQueue $regionStack;

    private \SplObjectStorage $regionContext;

    public readonly ExtendedState $extendedState;

    public function __construct()
    {
        $this->regionStack = new \SplQueue();
        $this->regionContext = new \SplObjectStorage();
        $this->history = new History($this);
    }

    public function push(Region $region)
    {
        $this->regionStack->push($region);
        if (!$this->regionContext->contains($region)) {
            $this->setRegionContext($region, []);
        }
    }

    public function pop()
    {
        return $this->regionStack->pop();
    }



    public function setRegionContext(Region $region, array $context)
    {
        $this->regionContext->attach($region, $context);
    }
}
