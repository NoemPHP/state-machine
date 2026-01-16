import React, { useState } from 'react';
import { Box, Text, useInput } from 'ink';
import TextInput from 'ink-text-input';
import type { PromptRequest, PromptResponse } from '../types/interactions.js';
import { createPromptResponse } from '../types/interactions.js';

interface PromptInteractionProps {
  request: PromptRequest;
  onResponse: (response: PromptResponse) => void;
}

export function PromptInteraction({ request, onResponse }: PromptInteractionProps) {
  const [value, setValue] = useState(request.data.defaultValue || '');
  const [error, setError] = useState<string | null>(null);

  const validate = (input: string): boolean => {
    if (!request.data.validation) return true;
    try {
      const regex = new RegExp(request.data.validation);
      return regex.test(input);
    } catch {
      return true; // Invalid regex, skip validation
    }
  };

  const handleSubmit = (input: string) => {
    if (!validate(input)) {
      setError(`Input must match pattern: ${request.data.validation}`);
      return;
    }
    onResponse(createPromptResponse(request.correlationId, input));
  };

  useInput((input, key) => {
    if (key.escape) {
      onResponse(createPromptResponse(request.correlationId, null, true));
    }
  });

  return (
    <Box flexDirection="column" marginY={1}>
      <Box marginBottom={1}>
        <Text bold color="cyan">? </Text>
        <Text bold>{request.data.question}</Text>
      </Box>

      {request.data.context && (
        <Box marginBottom={1} marginLeft={2}>
          <Text dimColor>{request.data.context}</Text>
        </Box>
      )}

      <Box marginLeft={2}>
        <Text color="cyan">❯ </Text>
        <TextInput
          value={value}
          onChange={(newValue) => {
            setValue(newValue);
            setError(null);
          }}
          onSubmit={handleSubmit}
          placeholder={request.data.placeholder || ''}
        />
      </Box>

      {error && (
        <Box marginTop={1} marginLeft={2}>
          <Text color="red">{error}</Text>
        </Box>
      )}

      <Box marginTop={1} marginLeft={2}>
        <Text dimColor>
          Type your response, Enter to submit, Esc to cancel
        </Text>
      </Box>
    </Box>
  );
}
