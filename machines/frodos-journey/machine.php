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
        $this->set('destination', 'UNKNOWN_DESTINATION');
        $this->set('diary', [
            'I have just left Hobbiton with Sam, Merry and Pippin.'
        ]);
        $this->set('diaryEveryKm', 4);
        $this->set('distanceSinceLastDiaryEntry', 3.9);
        $this->set('kilometresAhead', 0);
        $this->set('stepsPerKm', 2000);
        $this->set('stepsTotal', 0);
        $this->set('totalDistanceWalked', 0);
        $this->set('stepsInRoute', 0);
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
    };
}

function writeDiary()
{
    return function (object $t): Generator {
        $diary = $this->get('diary');
        $diaryEveryKm = $this->get('diaryEveryKm');
        $distanceSinceLastEntry = $this->get('distanceSinceLastDiaryEntry');
        $currentDiaryFragment = implode(PHP_EOL, array_slice($diary, -10));
        $this->set('currentDiaryFragment', $currentDiaryFragment);
        // Check if we have reached the threshold for writing a diary entry
        if ($distanceSinceLastEntry >= $diaryEveryKm) {
            $template = $this->template(
                <<<'EOF'
{{#complete temperature=0.8}}
<task>
We are following Frodo's travels through middle earth as part of a self-improvement fitness game.
Every couple of kilometres, Frodo is writing a diary entry about his journey.
While adhering to canon events and lore, aim to focus on health and fitness topics.
Examples include in-universe cooking recipes and mentions of physical activities that resemble workout sessions.
<journey>
This information is internal and allows you to assess where Frodo currently is.
In the reality of the adventure and the story we are telling, Frodo does not know about the actual distances.
Therefore, you MUST NOT let Frodo mention these numbers and units. 
You may refer to the time passed and allude to the distance travelled in "day marches".
Since writing the last diary entry, Frodo has walked {{distanceSinceLastDiaryEntry}}km.
Frodo still has {{remainingDistanceToDestination}}km ahead of him on the {{kilometresAhead}}km travel to the next destination: {{destination}}. 
In total, Frodo has walked {{totalDistanceWalked}}km so far.
</journey>
These are the past entries of Frodo's journey and serve as your long-term memory.
<diary lastEntries="10">
{{ currentDiaryFragment }}
</diary>
<instruction>
  Write a brief 1-paragraph diary entry from the perspective of Frodo.
  Consider how many days might have passed since the last entry and present a date at the beginning of your entry.
  Reflect on what events are taking place at this precise moment in the books.
  What would have happened in the time since the last entries.
  Who is accompanying you currently? 
  What have been important events and discussions among your peers?
  These are the things Frodo will write about.
  Focus on flow/continuity and avoid establishing/repeating things 
  that have been adressed in previous diary entries already.
  Pay attention to immersion and lore-friendliness. 
  Avoid repetitive journal entries and sprinkle in humour, drama, companionship and adventure depending on the context.
  Each new entry should have at least one unique memorable story to tell.
</instruction>
</task>
{{/complete}}
EOF
            );
            assert($template instanceof \Generator);
            $result = '';
            while ($template->valid()) {
                $chunk = $template->current();
                echo $chunk;
                $result .= $chunk;
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
        return $stepsInRoute >= $requiredSteps;
    };
}

function hasReachedDestination(): callable
{
    return function (object $t): void {
        echo 'FRODO WAS HERE LOLZ' . PHP_EOL;
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
