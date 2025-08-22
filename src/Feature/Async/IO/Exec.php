<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async\IO;

use Generator;
use SplFileObject;

class Exec
{

    private int $pid = 0;

    private string $tempFilePath;

    private ?int $timeout = null;

    /**
     * @throws \Exception
     */
    public function __construct(readonly string $command, ?int $timeout = null)
    {
        $this->timeout = $timeout;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(): Generator
    {
        // Open a process resource to capture the exit status
        $process = proc_open($this->command, [
            0 => ['pipe', 'r'],  // stdin is a pipe that the child will read from
            1 => ['pipe', 'w'],  // stdout is a pipe that the child will write to
            2 => ['pipe', 'a'],  // stderr is a pipe to append to
            //1 => ['file', $this->tempFilePath, 'w'],  // stdout is a file to write to
            //2 => ['file', $this->tempFilePath, 'a'],   // stderr is a file to append to
        ], $pipes);

        if (!is_resource($process)) {
            throw new \Exception('Failed to open process');
        }
        fclose($pipes[0]); // Close stdin
        stream_set_blocking($pipes[1], false);

        // Set up a timer for the timeout, if specified
        if ($this->timeout !== null) {
            $startTime = microtime(true);
        }

        //yield ''; // Yield control back to the coroutine
        //$stdOut = new StreamHandler($pipes[1]);
        //$stdOutGenerator = $stdOut();

        while (true) {
            $output = fgets($pipes[1], 4096);
            if ($output) {
                yield $output;
            }
            $status = proc_get_status($process);
            if (!$status['running']) {
                fclose($pipes[1]);

                //unlink($this->tempFilePath);

                return $status['exitcode'];
            }
            if ($this->timeout !== null && (microtime(true) - $startTime) > $this->timeout) {
                proc_terminate($process);
                //unlink($this->tempFilePath);
                yield 'Process execution timed out';

                return 1;
            }
            //$stdOutGenerator->next();
        }

        return 1;
    }
}
