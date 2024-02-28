<?php

namespace Noem\State;

use Symfony\Component\Yaml\Yaml;

class RegionLoader
{

    public static function fromYaml(string $yaml): RegionBuilder
    {
        $array = Yaml::parse($yaml);

        return self::fromArray($array);
    }

    public static function fromArray(array $array): RegionBuilder
    {
        $builder = new RegionBuilder();
        $states = $array['states'] ?? [];
        $regions = $array['regions'] ?? [];
        $builder->setStates($states);
        foreach ($regions as $state => $subRegions) {
            foreach ($subRegions as $region) {
                $builder->addRegion($state, self::fromArray($region));
            }
        }
        isset($array['initial']) && $builder->markInitial($array['initial']);
        isset($array['final']) && $builder->markInitial($array['final']);

        return $builder;
    }
}
