<?php

declare(strict_types=1);

use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\IO\Exec;

return [
    'onEnter.run_tests' => function (object $t) {
        // Simulate asynchronous execution using a generator
        yield;
        $exec = new Exec('vendor/bin/phpunit --display-all-issues --group middleware', null)();
        $result = '';
        while ($exec->valid()) {
            $chunk = $exec->current();
            $result .= $chunk;
            echo $chunk;
            $exec->next();
        }
        $return = $exec->getReturn();
        $this->set('phpunitResult', $result);
        $this->set('return', $return);
        yield;
    },
    'onEnter.investigate' => function (object $t) {
        // Simulate asynchronous execution using a generator
        yield;
        echo PHP_EOL;
        $messageGenerator = $this->template(
            <<<'EOF'
{{#complete}}
<command_result>
{{ phpunitResult }}
</command_result>
<instruction>
Investigate why the tests failed
</instruction>
{{/complete}}
EOF
        );
        while ($messageGenerator->valid()) {
            $chunk = $messageGenerator->current();
            echo $chunk;
            $messageGenerator->next();
            yield;
        }
        echo PHP_EOL;
        $this->set('goodbyeResult', true);
        yield;
    },
    'transition.guard.run_tests.investigate' => function (object $t): bool {
        $return = $this->get('return');

        return $return === 1;
    },
    'transition.guard.run_tests.goodbye' => function (object $t): bool {
        $return = $this->get('return');

        return $return === 0;
    },
    'transition.guard.investigate.run_tests' => function (object $t): bool {
        $return = $this->get('return');

        return $return === 0;
    },
    'onEnter.goodbye' => function (object $t) {
        // Simulate asynchronous execution using a generator
        yield;
        echo PHP_EOL;
        $messageGenerator = $this->template(
            <<<'EOF'
{{#complete}}
<command_result>
{{ phpunitResult }}
</command_result>
<instruction>
Say something about this command output
</instruction>
{{/complete}}
EOF
        );
        while ($messageGenerator->valid()) {
            $chunk = $messageGenerator->current();
            echo $chunk;
            $messageGenerator->next();
            yield;
        }
        echo PHP_EOL;
        $this->set('goodbyeResult', true);
        yield;
    },
    'transition.guard.goodbye.final' => function (object $t): bool {
        $return = $this->get('goodbyeResult');

        return $return !== null;
    },
];
