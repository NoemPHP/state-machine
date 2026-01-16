import React, { useState, useEffect, useRef, useLayoutEffect } from 'react';
import { Box, Text, useApp, useInput } from 'ink';
import TextInput from 'ink-text-input';
import Spinner from 'ink-spinner';
import { useProcessWrapper } from './hooks/useProcessWrapper.js';
import { InteractionHandler } from './components/InteractionHandler.js';

let lineId = 0;

// Clear screen and move cursor to home
const clearScreen = () => {
  process.stdout.write('\x1b[2J\x1b[H');
};

interface AppProps {
  runtimeDir: string;
}

export function App({ runtimeDir }: AppProps) {
  const { exit } = useApp();

  const {
    connected,
    error,
    stdout,
    currentInteraction,
    manifest,
    sendInput,
    sendResponse,
    disconnect,
  } = useProcessWrapper(runtimeDir);

  const [inputValue, setInputValue] = useState('');
  const [messages, setMessages] = useState<Array<{id: number; text: string}>>([]);
  const lastStdoutIndexRef = useRef(0);

  // Handle submit
  const handleSubmit = (value: string) => {
    const trimmed = value.trim();
    if (trimmed) {
      setMessages(prev => [
        ...prev,
        { id: lineId++, text: `❯ ${trimmed}` }
      ]);
      sendInput(trimmed);
      setInputValue('');
    }
  };

  // Handle Ctrl+C
  useInput((input, key) => {
    if (!connected || currentInteraction) return;
    if (key.ctrl && input === 'c') {
      disconnect();
      exit();
    }
  });

  // Transfer stdout to messages
  useEffect(() => {
    if (stdout.length > lastStdoutIndexRef.current) {
      const newLines = stdout
        .slice(lastStdoutIndexRef.current)
        .map(text => ({ id: lineId++, text }));
      if (newLines.length > 0) {
        setMessages(prev => [...prev, ...newLines]);
      }
      lastStdoutIndexRef.current = stdout.length;
    }
  }, [stdout]);

  // Clear screen before each render when content changes
  useLayoutEffect(() => {
    clearScreen();
  }, [messages, currentInteraction, connected]);

  // Render the interactive area
  const renderPrompt = () => {
    if (!connected) {
      return (
        <Box>
          <Text color="cyan"><Spinner type="dots" /></Text>
          <Text> Connecting to {runtimeDir}...</Text>
        </Box>
      );
    }

    if (currentInteraction) {
      return (
        <InteractionHandler
          key={currentInteraction.correlationId}
          request={currentInteraction}
          onResponse={sendResponse}
        />
      );
    }

    return (
      <Box>
        <Text color="cyan">❯ </Text>
        <TextInput
          value={inputValue}
          onChange={setInputValue}
          onSubmit={handleSubmit}
          placeholder="Enter command..."
        />
      </Box>
    );
  };

  return (
    <Box flexDirection="column" paddingX={1}>
      {/* Message history */}
      <Box flexDirection="column">
        {messages.map(msg => (
          <Text key={msg.id}>{msg.text}</Text>
        ))}
      </Box>

      {/* Interactive prompt */}
      {renderPrompt()}

      {/* Error */}
      {error && <Text color="red">Error: {error}</Text>}

      {/* Status */}
      <Box marginTop={1}>
        <Text dimColor>
          {connected
            ? `[${manifest?.mode || '?'}] PID:${manifest?.pid || '?'} | Ctrl+C to exit`
            : 'Connecting...'}
        </Text>
      </Box>
    </Box>
  );
}
