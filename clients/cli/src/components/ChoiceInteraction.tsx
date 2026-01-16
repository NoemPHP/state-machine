import React, { useState } from 'react';
import { Box, Text, useInput } from 'ink';
import type { ChoiceRequest, ChoiceResponse } from '../types/interactions.js';
import { createChoiceResponse } from '../types/interactions.js';

interface ChoiceInteractionProps {
  request: ChoiceRequest;
  onResponse: (response: ChoiceResponse) => void;
}

export function ChoiceInteraction({ request, onResponse }: ChoiceInteractionProps) {
  const options = Object.entries(request.data.options);
  const [focusIndex, setFocusIndex] = useState(0);
  const [selected, setSelected] = useState<Set<string>>(
    new Set(request.data.defaultKeys || [])
  );
  const [error, setError] = useState<string | null>(null);

  const { minSelections, maxSelections } = request.data;

  const toggleSelection = (key: string) => {
    setError(null);
    setSelected(prev => {
      const next = new Set(prev);
      if (next.has(key)) {
        next.delete(key);
      } else {
        // Check max selections
        if (maxSelections && next.size >= maxSelections) {
          setError(`Maximum ${maxSelections} selection(s) allowed`);
          return prev;
        }
        next.add(key);
      }
      return next;
    });
  };

  const submit = () => {
    if (selected.size < minSelections) {
      setError(`Minimum ${minSelections} selection(s) required`);
      return;
    }
    onResponse(createChoiceResponse(request.correlationId, Array.from(selected)));
  };

  useInput((input, key) => {
    if (key.upArrow) {
      setFocusIndex(i => (i > 0 ? i - 1 : options.length - 1));
    } else if (key.downArrow) {
      setFocusIndex(i => (i < options.length - 1 ? i + 1 : 0));
    } else if (input === ' ') {
      const [key] = options[focusIndex];
      toggleSelection(key);
    } else if (key.return) {
      submit();
    } else if (key.escape || input === 'q') {
      onResponse(createChoiceResponse(request.correlationId, [], true));
    }
  });

  return (
    <Box flexDirection="column" marginY={1}>
      <Box marginBottom={1}>
        <Text bold color="cyan">? </Text>
        <Text bold>{request.data.question}</Text>
        <Text dimColor>
          {' '}(select {minSelections}
          {maxSelections ? `-${maxSelections}` : '+'})
        </Text>
      </Box>

      {request.data.context && (
        <Box marginBottom={1} marginLeft={2}>
          <Text dimColor>{request.data.context}</Text>
        </Box>
      )}

      {options.map(([key, option], index) => {
        const isSelected = selected.has(key);
        const isFocused = index === focusIndex;

        return (
          <Box key={key} marginLeft={2}>
            <Text color={isFocused ? 'cyan' : undefined}>
              {isFocused ? '❯ ' : '  '}
            </Text>
            <Text color={isSelected ? 'green' : 'gray'}>
              {isSelected ? '◉' : '○'}
            </Text>
            <Text> </Text>
            <Text
              color={isFocused ? 'cyan' : undefined}
              bold={isFocused || isSelected}
            >
              {option.label}
            </Text>
            {option.recommended && (
              <Text color="yellow"> (recommended)</Text>
            )}
            {option.description && (
              <Text dimColor> - {option.description}</Text>
            )}
          </Box>
        );
      })}

      {error && (
        <Box marginTop={1} marginLeft={2}>
          <Text color="red">{error}</Text>
        </Box>
      )}

      <Box marginTop={1} marginLeft={2}>
        <Text dimColor>
          Use ↑↓ to navigate, Space to toggle, Enter to submit ({selected.size} selected), Esc to cancel
        </Text>
      </Box>
    </Box>
  );
}
