<?php

namespace Noem\State\Feature\Template\Compiler;

enum NodeType
{
    case TEXT;
    case VARIABLE_ESCAPE;
    case VARIABLE_UNESCAPE;
    case SECTION_OPEN;
    case SECTION_CLOSE;
}
