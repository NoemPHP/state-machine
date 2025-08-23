<?php

declare(strict_types=1);

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\Machine;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\Feature\Template\TemplateFeature;

require __DIR__ . '/../../vendor/autoload.php';

function bootstrap()
{
    return function (object $t): void {
        $this->set('destination', 'hobbiton');
        $this->set('diary', [
            'I have just left Hobbiton with Sam, Merry and Pippin.'
        ]);
        $this->set('distanceSinceLastDiaryEntry', 1.9);
        $this->set('distanceSinceLastSettingUpdate', 3.9);
        $this->set('kilometresAhead', PHP_INT_MAX);
        $this->set('stepsPerKm', 2000);
        $this->set('stepsTotal', 0);
        $this->set('totalDistanceWalked', 0);
        $this->set('stepsInRoute', 0);
        $this->set('lastDestination', 'hobbiton'); // Initialize lastDestination
        $this->set('currentSetting', ''); // Initialize currentSetting
    };
}

function takeStep()
{
    return function (object $t): void {
        $this->set('stepsTotal', $this->get('stepsTotal') + 1);
        $this->set('stepsInRoute', $this->get('stepsInRoute') + 1);

        // Calculate the total distance walked
        $totalDistanceWalked = $this->get('stepsTotal') / $this->get('stepsPerKm');

        // Calculate the remaining distance to the next destination
        $remainingDistance = $this->get('kilometresAhead') - ($this->get('stepsInRoute') / $this->get('stepsPerKm'));

        // Update distanceSinceLastDiaryEntry
        $distanceSinceLastEntry = $this->get('distanceSinceLastDiaryEntry');
        $distanceCoveredThisStep = 1 / $this->get(
                'stepsPerKm'
            ); // Assuming each step covers 1 km divided by steps per km
        $this->set('distanceSinceLastDiaryEntry', $distanceSinceLastEntry + $distanceCoveredThisStep);
        $this->set('remainingDistanceToDestination', $remainingDistance);
        $this->set('totalDistanceWalked', $totalDistanceWalked);

        // Update distanceSinceLastSettingUpdate
        $distanceSinceLastSettingUpdate = $this->get('distanceSinceLastSettingUpdate');
        $this->set('distanceSinceLastSettingUpdate', $distanceSinceLastSettingUpdate + $distanceCoveredThisStep);
    };
}

function writeDiary(int $everyKm)
{
    return function (object $t) use ($everyKm): Generator {
        $diary = $this->get('diary');
        $distanceSinceLastEntry = $this->get('distanceSinceLastDiaryEntry');
        $currentDiaryFragment = implode(PHP_EOL, array_slice($diary, -10));
        $this->set('currentDiaryFragment', $currentDiaryFragment);
        // Check if we have reached the threshold for writing a diary entry
        if ($distanceSinceLastEntry > $everyKm) {
            echo PHP_EOL;
            echo "---------------------DIARY--------------------------";
            echo PHP_EOL;
            echo PHP_EOL;

            $template = $this->template(
                <<<'EOF'
{{#complete temperature=0.8}}
    We are following Frodo's travels through middle earth as part of a self-improvement fitness game.
    Every couple of kilometres, Frodo is writing a diary entry about his journey.
    While adhering to canon events and lore, aim to focus on health and fitness topics.
    Examples include in-universe cooking recipes and mentions of physical activities that resemble workout sessions.
## Current Setting
    {{currentSetting}}
## Diary (last 10 entries)
    {{ currentDiaryFragment }}

## Instructions
    Write a brief 1-paragraph diary entry from the perspective of Frodo.
    Consider how many days might have passed since the last entry and present a date at the beginning of your entry.
    Reflect on what events are taking place at this precise moment in the books.
    What would have happened in the time since the last entries.
    Who is accompanying you currently? 
    What have been important events and discussions among your peers?
    These are the things Frodo will write about.
    Focus on flow/continuity and avoid establishing/repeating things 
    that have been addressed in previous diary entries already.
    Pay attention to immersion and lore-friendliness. 
    Avoid repetitive journal entries and sprinkle in humour, drama, companionship and adventure depending on the context.
    Each new entry should have at least one unique memorable story to tell.
{{/complete}}
EOF
            );
            assert($template instanceof \Generator);
            $result = '';
            while ($template->valid()) {
                $chunk = $template->current();
                $result .= $chunk;
                echo $chunk;
                $template->next();
                yield;
            }
            $diary[] = $result;

            // Update the diary and reset distanceSinceLastDiaryEntry
            $this->set('diary', $diary);
            $this->set('distanceSinceLastDiaryEntry', 0);

            echo PHP_EOL;
            echo "----------------------------------------------------";
            echo PHP_EOL;
        }
    };
}

function assessSetting(int $everyKm)
{
    return function (object $t) use ($everyKm): Generator {
        $distanceSinceLastSettingUpdate = $this->get('distanceSinceLastSettingUpdate');
        // Check if we have reached the threshold for a setting update
        if ($distanceSinceLastSettingUpdate > $everyKm) {
            echo PHP_EOL;
            echo "--------------------SETTING-------------------------";
            echo PHP_EOL;
            echo PHP_EOL;
            $template = $this->template(
                <<<'EOF'
{{#complete temperature=0.8}}
    You are a *Lord of the Rings* expert tasked with faithfully assessing Frodo's current location and company based on the available information.
    Adhere strictly to the lore and accuracy of *The Lord of the Rings*.
    You are an arbiter of the plot and responsible to setting things in motion.
    Do not invent details or events not present in the books.
## Diary (last 10 entries)
    {{ currentDiaryFragment }}
## Previous Setting Assessment
{{currentSetting}}

## Instructions
    Frodo is currently traveling from {{lastDestination}} to {{destination}}.
    He is {{distanceSinceLastDiaryEntry}}km from his last diary entry.
    His destination is still {{kilometresAhead}}km away.
    Based on this information, describe the likely companions, terrain, weather, mood, and overall situation Frodo finds himself in.
    Focus the the CURRENT situation, avoid leaking details about future events not known to Frodo.
    Take note of any changes compared to the last assessment.
    Specify major story events that have taken place since the last assessment.
    Be as precise and lore-accurate as possible, drawing on your profound knowledge of Middle-earth.
    Summarize the travel so far, translating distances to day marches..
    However, be EXTREMELY brief and concise. Your output will be fed directly into the context of another LLM.
{{/complete}}
EOF
            );
            assert($template instanceof \Generator);
            $result = '';
            while ($template->valid()) {
                $chunk = $template->current();
                $result .= $chunk;
                echo $chunk;
                $template->next();
                yield;
            }
            $this->set('currentSetting', $result);
            $this->set('distanceSinceLastSettingUpdate', 0);
            echo PHP_EOL;
            echo "----------------------------------------------------";
            echo PHP_EOL;
        };
    };
}

function setDistanceToDestination(int $distanceInKilometres, string $destination)
{
    return function (object $t) use ($distanceInKilometres, $destination): void {
        $this->set('destination', $destination);
        $this->set('kilometresAhead', $distanceInKilometres);
    };
}

function isDestinationReached(): callable
{
    return function (object $t): bool {
        $kilometresAhead = $this->get('kilometresAhead');
        $stepsPerKm = $this->get('stepsPerKm');
        $stepsInRoute = $this->get('stepsInRoute');

        // Calculate the required steps to reach the destination
        $requiredSteps = $kilometresAhead * $stepsPerKm;

        // Return true if the steps taken are sufficient
        return $stepsInRoute > $requiredSteps;
    };
}

function hasReachedDestination(): callable
{
    return function (object $t): void {
        $this->set('lastDestination', $this->get('destination'));
        $this->set('kilometresAhead', PHP_INT_MAX);
        $this->get('stepsInRoute', 0);

        $message = 'I have have reached the destination ' . $this->get('destination');

        echo PHP_EOL;
        echo "----------------NEW DESTINATION---------------------";
        echo PHP_EOL;
        echo PHP_EOL;
        echo $message;
        echo PHP_EOL;
        echo "----------------------------------------------------";
        echo PHP_EOL;
        $diary = $this->get('diary');
        $diary[] = 'I have have reached the destination ' . $this->get('destination');
        $this->set('diary', $diary);
    };
}

return Machine::run(
    new class extends Machine {
        public function features(): iterable
        {
            return array_merge(
                [
                    new ExtendedState(),
                    new TemplateFeature(),
                    new AiFeature(),
                    new AsyncFeature(),
                    new OrthogonalRegions(),
                    new JsonSchemaFeature(),
                ],
                parent::features()
            );
        }

        public function trigger(): object
        {
            return new stdClass();
        }

        public function container(): array
        {
            return include_once __DIR__ . '/container.php';
        }

        public function yaml(): string
        {
            return file_get_contents(__DIR__ . '/journey.yml');
        }
    }
);
