<?php

declare(strict_types=1);

// Module-level functions for Holon conversational CLI

function onEnterIdle()
{
    return function (object $t): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "   CONVERSATIONAL CLI MACHINE\n";
        echo "   Powered by Noem State Machine + Claude AI\n";
        echo str_repeat('=', 60) . "\n\n";
        echo "Type your messages and press Enter to chat.\n";
        echo "Type 'exit' or 'quit' to end the conversation.\n\n";

        $this->set('conversation_history', []);
        $this->set('user_input', null);
        $this->set('ai_response', null);
        $this->set('should_exit', false);
    };
}

function onEnterWaitingForInput()
{
    return function (object $t): void {
        echo "\n> ";
    };
}

function actionWaitingForInput()
{
    return function (object $t): Generator {
        // Use STDIN constant directly - don't open/close it
        // Read one line in blocking mode
        $input = fgets(STDIN);

        if ($input !== false) {
            $userInput = trim($input);

            if (!empty($userInput)) {
                // Got input - process it
                if (in_array(strtolower($userInput), ['exit', 'quit', 'bye'])) {
                    $this->set('should_exit', true);
                    $this->set('user_input', $userInput);
                    return;
                }

                $this->set('user_input', $userInput);

                $history = $this->get('conversation_history');
                $history[] = ['role' => 'user', 'content' => $userInput];
                $this->set('conversation_history', $history);

                return;
            }
        }

        // Empty input or false - yield and try again
        yield;
    };
}

function guardHasUserInput()
{
    return function (object $t): bool {
        return $this->get('user_input') !== null;
    };
}

function onEnterProcessing()
{
    return function (object $t): void {
        echo "\nAssistant: ";
        flush();
    };
}

function actionProcessing()
{
    return function (object $t): Generator {
        $history = $this->get('conversation_history');

        $conversationContext = "";
        foreach ($history as $entry) {
            $role = ucfirst($entry['role']);
            $conversationContext .= "$role: {$entry['content']}\n";
        }

        $this->set('conversationContext', $conversationContext);

        $template = $this->template(
            <<<'TEMPLATE'
{{#complete temperature=0.7 max=500}}
You are a helpful, friendly AI assistant engaged in a conversation via CLI.

Conversation History:
{{conversationContext}}

Instructions:
- Provide helpful, concise, and friendly responses
- Keep responses relatively brief (2-4 sentences typically)
- Be conversational and natural
- Don't use markdown formatting
- Don't start with "Assistant:" or similar prefixes
{{/complete}}
TEMPLATE
        );

        assert($template instanceof Generator);

        $fullResponse = '';
        while ($template->valid()) {
            $chunk = $template->current();
            $fullResponse .= $chunk;
            echo $chunk;
            flush();
            $template->next();
            yield;
        }

        $this->set('ai_response', $fullResponse);

        $history[] = ['role' => 'assistant', 'content' => $fullResponse];
        $this->set('conversation_history', $history);

        echo "\n";
    };
}

function guardHasAiResponse()
{
    return function (object $t): bool {
        return $this->get('ai_response') !== null;
    };
}

function onEnterResponding()
{
    return function (object $t): void {
        $this->set('user_input', null);
        $this->set('ai_response', null);
    };
}

function actionResponding()
{
    return function (object $t): Generator {
        usleep(100000);
        yield;
    };
}

function guardResponseComplete()
{
    return function (object $t): bool {
        return !$this->get('should_exit');
    };
}

function guardConversationEnded()
{
    return function (object $t): bool {
        return $this->get('should_exit') === true;
    };
}

function onEnterFinished()
{
    return function (object $t): void {
        $history = $this->get('conversation_history');
        $messageCount = count($history);

        echo "\n" . str_repeat('-', 60) . "\n";
        echo "Conversation ended.\n";
        echo "Total messages exchanged: $messageCount\n";
        echo "Thank you for chatting!\n";
        echo str_repeat('-', 60) . "\n\n";
    };
}
