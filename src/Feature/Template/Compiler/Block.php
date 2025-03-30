<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template\Compiler;

interface Block
{
    const TEXT_LINE = '\r\t$this->append( \'%s\'.\n );';
    const TEXT_LAST = '\r\t$this->append( \'%s\' );';
    const ESCAPE_VALUE = '\r\t$this->append( $this->>stringify( $this->find(\'%s\') ) );\r';
    const VARIABLE_VALUE = '\r\t$this->append( $this->escape($this->stringify($this->find(\'%s\') ) ) );\r';
    const ESCAPE_HELPER_OPEN = '\r\t$this->append( $this->helper(\'%s\',';
    const ESCAPE_HELPER_CLOSE = '\r\t));\r';
    const VARIABLE_HELPER_OPEN = '\r\t$this->append( $this->escape($this->helper(\'%s\',';
    const VARIABLE_HELPER_CLOSE = '\r\t)));\r';
    const ARGUMENT_VALUE = '\'%s\'';
    const OPTIONS_OPEN = 'array(';
    const OPTIONS_CLOSE = '\r\t)';
    const OPTIONS_FN_OPEN = '\r\t\'fn\' => function($context = null) {';
    const OPTIONS_FN_BODY_1 = '\r\t\1if(is_array($context)) {';
    const OPTIONS_FN_BODY_2 = '\r\t\1\1$this->push($context);';
    const OPTIONS_FN_BODY_3 = '\r\t\1}';
    const OPTIONS_FN_BODY_4 = '\r\r\t\1$buffer = [];';
    const OPTIONS_FN_BODY_5 = '\r\r\t\1if(is_array($context)) {';
    const OPTIONS_FN_BODY_6 = '\r\t\1\1$this->pop();';
    const OPTIONS_FN_BODY_7 = '\r\t\1}';
    const OPTIONS_FN_CLOSE = '\r\r\t\1\t},\r';
    const OPTIONS_INVERSE_OPEN = '\r\t\'inverse\' => function($context = null) use ($data, &$helper) {';
    const OPTIONS_INVERSE_BODY_1 = '\r\t\1if(is_array($context)) {';
    const OPTIONS_INVERSE_BODY_2 = '\r\t\1\1$data->push($context);';
    const OPTIONS_INVERSE_BODY_3 = '\r\t\1}';
    const OPTIONS_INVERSE_BODY_4 = '\r\r\t\1$buffer = \'\';';
    const OPTIONS_INVERSE_BODY_5 = '\r\r\t\1if(is_array($context)) {';
    const OPTIONS_INVERSE_BODY_6 = '\r\t\1\1$data->pop();';
    const OPTIONS_INVERSE_BODY_7 = '\r\t\1}';
    const OPTIONS_INVERSE_CLOSE = '\r\r\t\1return $buffer;\r\t}\r';
    const OPTIONS_FN_EMPTY = '\r\t\'fn\' => fn() => null,';
    const OPTIONS_INVERSE_EMPTY = '\r\t\'inverse\' => fn() => null';
    const OPTIONS_NAME = '\r\t\'name\' => \'%s\',';
    const OPTIONS_ARGS = '\r\t\'args\' => array(%s),';
    const OPTIONS_HASH = '\r\t\'hash\' => array(%s),';
    const OPTIONS_WRAP = '\r\t\'wrap\' => array(%s),';
    const OPTIONS_HASH_KEY_VALUE = '\'%s\' => %s';
}
