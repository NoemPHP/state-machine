<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template\Compiler;

class Tokenizer
{
    /**
     * @var string $buffer
     */
    protected string $buffer = '';

    protected string $bars = '{}';

    protected NodeType $type = NodeType::TEXT;

    /**
     * @var int $level
     */
    protected int $level = 0;

    public function __construct(
        private readonly string $source,
    ) {
    }

    /**
     * @return \Generator<Node>
     */
    public function process(): \Generator
    {
        $length = strlen($this->source);
        for ($line = 1, $i = 0; $i < $length; $i++) {
            if ($this->source[$i] == "\n") {
                $line++;
            }

            $doubleBars = $this->bars[0] . $this->bars[0];
            $tripleBars = $doubleBars . $this->bars[0];

            switch (true) {
                //section
                case substr($this->source, $i, 3) == $tripleBars . '#':
                    yield from $this->createNode($i, NodeType::SECTION_OPEN, $line, 4, 6, $i);
                    break;
                case substr($this->source, $i, 3) == $doubleBars . '#':
                    yield from $this->createNode($i, NodeType::SECTION_OPEN, $line, 3, 5, $i);
                    break;
                case substr($this->source, $i, 3) == $tripleBars . '/':
                    yield from $this->createNode($i, NodeType::SECTION_CLOSE, $line, 4, 6, $i);
                    break;
                case substr($this->source, $i, 3) == $doubleBars . '/':
                    yield from $this->createNode($i, NodeType::SECTION_CLOSE, $line, 3, 5, $i);
                    break;

                //variable
                case substr($this->source, $i, 3) == $tripleBars:
                    yield from $this->createNode($i, NodeType::VARIABLE_ESCAPE, $line, 3, 6, $i);
                    break;
                case substr($this->source, $i, 2) == $doubleBars:
                    yield from $this->createNode($i, NodeType::VARIABLE_UNESCAPE, $line, 2, 4, $i);
                    break;

                //text
                default:
                    $this->buffer .= $this->source[$i];
                    break;
            }
        }
        yield from $this->flushText($i, $line);
    }

    protected function createNode(
        int $start,
        NodeType $type,
        int $line,
        int $offset1,
        int $offset2,
        int &$i
    ): \Generator {
        yield from $this->flushText($start, $line);

        switch ($type) {
            case NodeType::VARIABLE_ESCAPE:
                $end = $this->findVariable($start, true);
                break;
            case NodeType::SECTION_OPEN:
            case NodeType::VARIABLE_UNESCAPE:
                $end = $this->findVariable($start, false);
                break;
            case NodeType::SECTION_CLOSE:
            default:
                $end = $this->findVariable($start, false);
                $this->level--;
                break;
        }
        yield new Node(
            $type,
            $line,
            $start,
            $end,
            $this->level,
            substr($this->source, $start + $offset1, $end - $start - $offset2)
        );

        if ($type === NodeType::SECTION_OPEN) {
            $this->level++;
        }

        $i = $end - 1;
    }

    private function findVariable(int $i, bool $escape): int
    {
        $close = $this->bars[1] . $this->bars[1];

        if ($escape) {
            $close = $close . $this->bars[1];
        }

        for (; substr($this->source, $i, strlen($close)) !== $close; $i++) {
            //Silence
        }

        return $i + strlen($close);
    }

    private function flushText(int $i, int $line): \Generator
    {
        if ($this->type !== NodeType::TEXT || !strlen($this->buffer)) {
            return yield from [];
        }
        yield new Node(
            $this->type,
            $line,
            $i - strlen($this->buffer),
            $i - 1,
            $this->level,
            $this->buffer,
        );
        //flush
        $this->buffer = '';
    }
}
