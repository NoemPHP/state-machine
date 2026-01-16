import React from 'react';
import { Box, Text } from 'ink';
import type { InteractionRequest, InteractionResponse } from '../types/interactions.js';
import {
  isConfirmRequest,
  isSelectRequest,
  isChoiceRequest,
  isPromptRequest,
} from '../types/interactions.js';
import { ConfirmInteraction } from './ConfirmInteraction.js';
import { SelectInteraction } from './SelectInteraction.js';
import { ChoiceInteraction } from './ChoiceInteraction.js';
import { PromptInteraction } from './PromptInteraction.js';

interface InteractionHandlerProps {
  request: InteractionRequest;
  onResponse: (response: InteractionResponse) => void;
}

/**
 * Generic interaction handler that routes to the appropriate
 * interaction component based on the request type.
 */
export function InteractionHandler({ request, onResponse }: InteractionHandlerProps) {
  try {
    if (isConfirmRequest(request)) {
      return <ConfirmInteraction request={request} onResponse={onResponse} />;
    }

    if (isSelectRequest(request)) {
      return <SelectInteraction request={request} onResponse={onResponse} />;
    }

    if (isChoiceRequest(request)) {
      return <ChoiceInteraction request={request} onResponse={onResponse} />;
    }

    if (isPromptRequest(request)) {
      return <PromptInteraction request={request} onResponse={onResponse} />;
    }

    // Unknown interaction type - cast to access properties
    const unknownRequest = request as unknown as { type: string; correlationId: string };
    return (
      <Box flexDirection="column" marginY={1}>
        <Text color="red">Unknown interaction type: {unknownRequest.type}</Text>
        <Text dimColor>Correlation ID: {unknownRequest.correlationId}</Text>
      </Box>
    );
  } catch (error) {
    return (
      <Box flexDirection="column" marginY={1}>
        <Text color="red">
          Error handling interaction: {error instanceof Error ? error.message : String(error)}
        </Text>
      </Box>
    );
  }
}
