<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai;

use Noem\State\Middleware\Mesh;

class RequestBuilder extends Mesh
{

    private $model;

    private $prompt;

    private $maxTokens;

    private $temperature;

    private ?string $stop = null;

    private $logprobs = true;

    private $stream = false;

    private $responseFormat = null;

    private array $data;

    public function __construct(?array $data = null)
    {
        $this->data = $data ?? [
            'baseUrl' => 'http://telvanni:7863/v1',
            'token' => 'sk-111111111111111111111111111111111111111111111111',
            'model' => 'qwen2.5-coder:14b-instruct-q4_K_M',
            'prompt' => 'say hello',
            'logprobs' => true,
            'stop' => null,
            'maxTokens' => 2048,
            'temperature' => 0.4,
            'stream' => true,
        ];
        parent::__construct($this->data);
    }

    public function setModel(string $model): self
    {
        $this->offsetSet('model', $model);

        return $this;
    }

    public function setPrompt(string $prompt): self
    {
        $this->offsetSet('prompt', $prompt);

        return $this;
    }

    public function setMaxTokens(int $maxTokens): self
    {
        $this->offsetSet('maxTokens', $maxTokens);

        return $this;
    }

    public function setTemperature(float $temperature): self
    {
        $this->offsetSet('temperature', $temperature);

        return $this;
    }

    public function setStop($stop): self
    {
        $this->offsetSet('stop', $stop);

        return $this;
    }

    public function setResponseFormat(ResponseFormat $responseFormat): self
    {
        $this->offsetSet('responseFormat', $responseFormat);

        return $this;
    }

    public function new(): self
    {
        return new self($this->getArrayCopy());
    }

    public function build()
    {
        return new Request(
            $this->offsetGet('baseUrl'),
            $this->offsetGet('token'),
            $this->offsetGet('model'),
            $this->offsetGet('prompt'),
            $this->offsetGet('maxTokens'),
            $this->offsetGet('temperature'),
            $this->offsetGet('stop'),
            $this->offsetGet('responseFormat'),
            $this->offsetGet('logprobs'),
            $this->offsetGet('stream')
        );
    }
}
