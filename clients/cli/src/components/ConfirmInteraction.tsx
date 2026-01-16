import React, { useState } from 'react';
import { Box, Text, useInput } from 'ink';
import type { ConfirmRequest, ConfirmResponse } from '../types/interactions.js';
import { createConfirmResponse } from '../types/interactions.js';

interface ConfirmInteractionProps {
  request: ConfirmRequest;
  onResponse: (response: ConfirmResponse) => void;
}

export function ConfirmInteraction({ request, onResponse }: ConfirmInteractionProps) {
  const [selected, setSelected] = useState(request.data.defaultValue);

  useInput((input, key) => {
    if (key.leftArrow || key.rightArrow || input === 'h' || input === 'l') {
      setSelected(s => !s);
    } else if (input === 'y' || input === 'Y') {
      onResponse(createConfirmResponse(request.correlationId, true));
    } else if (input === 'n' || input === 'N') {
      onResponse(createConfirmResponse(request.correlationId, false));
    } else if (key.return) {
      onResponse(createConfirmResponse(request.correlationId, selected));
    } else if (key.escape || input === 'q') {
      onResponse(createConfirmResponse(request.correlationId, false, true));
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
        <Text
          color={selected ? 'cyan' : undefined}
          bold={selected}
          inverse={selected}
        >
          {' Yes '}
        </Text>
        <Text> / </Text>
        <Text
          color={!selected ? 'cyan' : undefined}
          bold={!selected}
          inverse={!selected}
        >
          {' No '}
        </Text>
      </Box>

      <Box marginTop={1} marginLeft={2}>
        <Text dimColor>
          Use ←→ to toggle, y/n for quick select, Enter to confirm, Esc to cancel
        </Text>
      </Box>
    </Box>
  );
}
