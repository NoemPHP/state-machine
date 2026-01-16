import React, { useState } from 'react';
import { Box, Text, useInput } from 'ink';
import type { SelectRequest, SelectResponse } from '../types/interactions.js';
import { createSelectResponse } from '../types/interactions.js';

interface SelectInteractionProps {
  request: SelectRequest;
  onResponse: (response: SelectResponse) => void;
}

export function SelectInteraction({ request, onResponse }: SelectInteractionProps) {
  const options = Object.entries(request.data.options);
  const defaultIndex = request.data.defaultKey
    ? options.findIndex(([key]) => key === request.data.defaultKey)
    : 0;

  const [selectedIndex, setSelectedIndex] = useState(Math.max(0, defaultIndex));

  useInput((input, key) => {
    if (key.upArrow) {
      setSelectedIndex(i => (i > 0 ? i - 1 : options.length - 1));
    } else if (key.downArrow) {
      setSelectedIndex(i => (i < options.length - 1 ? i + 1 : 0));
    } else if (key.return) {
      const [selectedKey] = options[selectedIndex];
      onResponse(createSelectResponse(request.correlationId, selectedKey));
    } else if (key.escape || input === 'q') {
      onResponse(createSelectResponse(request.correlationId, null, true));
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

      {options.map(([key, option], index) => (
        <Box key={key} marginLeft={2}>
          <Text color={index === selectedIndex ? 'cyan' : undefined}>
            {index === selectedIndex ? '❯ ' : '  '}
          </Text>
          <Text
            color={index === selectedIndex ? 'cyan' : undefined}
            bold={index === selectedIndex}
          >
            {option.label}
          </Text>
          {option.description && (
            <Text dimColor> - {option.description}</Text>
          )}
        </Box>
      ))}

      <Box marginTop={1} marginLeft={2}>
        <Text dimColor>
          Use ↑↓ to navigate, Enter to select, Esc to cancel
        </Text>
      </Box>
    </Box>
  );
}
