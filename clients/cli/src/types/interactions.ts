/**
 * Interaction types matching the PHP InteractionFeature classes.
 * These are JSON-serialized over the socket connection.
 */

// Base types
export type InteractionType = 'confirm' | 'select' | 'choice' | 'prompt';

export interface BaseInteractionRequest {
  type: string; // Fully qualified PHP class name
  correlationId: string;
  data: {
    question: string;
    context?: string | null;
    timeoutMs?: number | null;
  };
}

export interface BaseInteractionResponse {
  type: string;
  correlationId: string;
  data: {
    cancelled: boolean;
  };
}

// Confirm pattern
export interface ConfirmRequest extends BaseInteractionRequest {
  data: BaseInteractionRequest['data'] & {
    defaultValue: boolean;
  };
}

export interface ConfirmResponse extends BaseInteractionResponse {
  data: BaseInteractionResponse['data'] & {
    confirmed: boolean;
  };
}

// Select pattern (single choice)
export interface SelectOption {
  label: string;
  description?: string | null;
}

export interface SelectRequest extends BaseInteractionRequest {
  data: BaseInteractionRequest['data'] & {
    options: Record<string, SelectOption>;
    defaultKey?: string | null;
  };
}

export interface SelectResponse extends BaseInteractionResponse {
  data: BaseInteractionResponse['data'] & {
    selectedKey: string | null;
  };
}

// Choice pattern (multiple selection)
export interface ChoiceOption {
  label: string;
  description?: string | null;
  recommended: boolean;
}

export interface ChoiceRequest extends BaseInteractionRequest {
  data: BaseInteractionRequest['data'] & {
    options: Record<string, ChoiceOption>;
    defaultKeys: string[];
    minSelections: number;
    maxSelections?: number | null;
  };
}

export interface ChoiceResponse extends BaseInteractionResponse {
  data: BaseInteractionResponse['data'] & {
    selectedKeys: string[];
  };
}

// Prompt pattern (free text)
export interface PromptRequest extends BaseInteractionRequest {
  data: BaseInteractionRequest['data'] & {
    placeholder?: string | null;
    defaultValue?: string | null;
    validation?: string | null; // Regex pattern
    multiline: boolean;
  };
}

export interface PromptResponse extends BaseInteractionResponse {
  data: BaseInteractionResponse['data'] & {
    input: string | null;
  };
}

// Union types for generic handling
export type InteractionRequest =
  | ConfirmRequest
  | SelectRequest
  | ChoiceRequest
  | PromptRequest;

export type InteractionResponse =
  | ConfirmResponse
  | SelectResponse
  | ChoiceResponse
  | PromptResponse;

// Type guards
export function getInteractionType(request: InteractionRequest): InteractionType {
  const className = request.type;
  if (className.includes('ConfirmRequest')) return 'confirm';
  if (className.includes('SelectRequest')) return 'select';
  if (className.includes('ChoiceRequest')) return 'choice';
  if (className.includes('PromptRequest')) return 'prompt';
  throw new Error(`Unknown interaction type: ${className}`);
}

export function isConfirmRequest(req: InteractionRequest): req is ConfirmRequest {
  return getInteractionType(req) === 'confirm';
}

export function isSelectRequest(req: InteractionRequest): req is SelectRequest {
  return getInteractionType(req) === 'select';
}

export function isChoiceRequest(req: InteractionRequest): req is ChoiceRequest {
  return getInteractionType(req) === 'choice';
}

export function isPromptRequest(req: InteractionRequest): req is PromptRequest {
  return getInteractionType(req) === 'prompt';
}

// Response builders
export function createConfirmResponse(
  correlationId: string,
  confirmed: boolean,
  cancelled = false
): ConfirmResponse {
  return {
    type: 'Noem\\State\\Feature\\Interaction\\ConfirmResponse',
    correlationId,
    data: { confirmed, cancelled },
  };
}

export function createSelectResponse(
  correlationId: string,
  selectedKey: string | null,
  cancelled = false
): SelectResponse {
  return {
    type: 'Noem\\State\\Feature\\Interaction\\SelectResponse',
    correlationId,
    data: { selectedKey, cancelled },
  };
}

export function createChoiceResponse(
  correlationId: string,
  selectedKeys: string[],
  cancelled = false
): ChoiceResponse {
  return {
    type: 'Noem\\State\\Feature\\Interaction\\ChoiceResponse',
    correlationId,
    data: { selectedKeys, cancelled },
  };
}

export function createPromptResponse(
  correlationId: string,
  input: string | null,
  cancelled = false
): PromptResponse {
  return {
    type: 'Noem\\State\\Feature\\Interaction\\PromptResponse',
    correlationId,
    data: { input, cancelled },
  };
}
