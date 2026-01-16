<?php

declare(strict_types=1);

namespace ProcessWrapper;

/**
 * Manages the runtime directory for process wrapper IPC
 *
 * Creates and manages:
 * - Named pipes (FIFOs) for stdin
 * - Unix domain sockets for broadcast channels (stdout, stderr, interactions, logs)
 * - Manifest file advertising channels and capabilities
 */
class RuntimeDirectory
{
    private string $path;
    private bool $created = false;

    /** @var resource[] */
    private array $sockets = [];

    /** @var resource|null */
    private mixed $stdinFifo = null;

    public function __construct(
        private readonly int $pid,
        ?string $basePath = null
    ) {
        $base = $basePath ?? (getenv('XDG_RUNTIME_DIR') ?: '/tmp');
        $this->path = "{$base}/holon-{$pid}";
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function create(): void
    {
        if ($this->created) {
            return;
        }

        if (!mkdir($this->path, 0700, true) && !is_dir($this->path)) {
            throw new \RuntimeException("Failed to create runtime directory: {$this->path}");
        }

        // Write PID file
        file_put_contents("{$this->path}/pid", (string)$this->pid);

        // Create stdin FIFO
        $stdinPath = "{$this->path}/stdin.fifo";
        if (!posix_mkfifo($stdinPath, 0600)) {
            throw new \RuntimeException("Failed to create stdin FIFO: {$stdinPath}");
        }

        // Create/update holon-latest symlink for easy discovery
        $base = dirname($this->path);
        $latestLink = "{$base}/holon-latest";
        @unlink($latestLink);  // Remove existing symlink
        @symlink($this->path, $latestLink);

        $this->created = true;
    }

    public function createSocket(string $name): mixed
    {
        $socketPath = "{$this->path}/{$name}.sock";

        $socket = stream_socket_server(
            "unix://{$socketPath}",
            $errno,
            $errstr
        );

        if ($socket === false) {
            throw new \RuntimeException("Failed to create socket {$name}: {$errstr} ({$errno})");
        }

        stream_set_blocking($socket, false);
        chmod($socketPath, 0600);

        $this->sockets[$name] = $socket;

        return $socket;
    }

    public function getSocket(string $name): mixed
    {
        return $this->sockets[$name] ?? null;
    }

    public function openStdinFifo(): mixed
    {
        if ($this->stdinFifo !== null) {
            return $this->stdinFifo;
        }

        $stdinPath = "{$this->path}/stdin.fifo";

        // Open in read-write mode to prevent blocking
        $this->stdinFifo = fopen($stdinPath, 'r+');
        if ($this->stdinFifo === false) {
            throw new \RuntimeException("Failed to open stdin FIFO: {$stdinPath}");
        }

        stream_set_blocking($this->stdinFifo, false);

        return $this->stdinFifo;
    }

    public function writeManifest(array $capabilities = []): void
    {
        $channels = [
            'stdin' => [
                'type' => 'fifo',
                'path' => "{$this->path}/stdin.fifo",
                'direction' => 'in',
            ],
        ];

        foreach ($this->sockets as $name => $socket) {
            $channels[$name] = [
                'type' => 'socket',
                'path' => "{$this->path}/{$name}.sock",
                'direction' => 'out',
                'broadcast' => true,
            ];
        }

        $manifest = [
            'pid' => $this->pid,
            'started_at' => date('c'),
            'field_separator' => "\n",
            'channels' => $channels,
            'capabilities' => $capabilities,
        ];

        file_put_contents(
            "{$this->path}/manifest.json",
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function cleanup(): void
    {
        // Close sockets
        foreach ($this->sockets as $name => $socket) {
            if (is_resource($socket)) {
                fclose($socket);
            }
            $socketPath = "{$this->path}/{$name}.sock";
            if (file_exists($socketPath)) {
                unlink($socketPath);
            }
        }
        $this->sockets = [];

        // Close stdin FIFO
        if ($this->stdinFifo !== null && is_resource($this->stdinFifo)) {
            fclose($this->stdinFifo);
        }
        $this->stdinFifo = null;

        // Remove FIFO file
        $stdinPath = "{$this->path}/stdin.fifo";
        if (file_exists($stdinPath)) {
            unlink($stdinPath);
        }

        // Remove manifest and PID
        @unlink("{$this->path}/manifest.json");
        @unlink("{$this->path}/pid");

        // Remove directory
        if (is_dir($this->path)) {
            rmdir($this->path);
        }

        $this->created = false;
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
