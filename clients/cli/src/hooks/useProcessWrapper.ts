import { useEffect, useCallback, useRef, useSyncExternalStore } from 'react';
import * as net from 'node:net';
import * as fs from 'node:fs';
import * as path from 'node:path';
import type { InteractionRequest, InteractionResponse } from '../types/interactions.js';

export interface ProcessWrapperState {
  connected: boolean;
  error: string | null;
  stdout: string[];
  stderr: string[];
  logs: string[];
  currentInteraction: InteractionRequest | null;
  manifest: ProcessManifest | null;
}

export interface ProcessManifest {
  features: string[];
  mode: 'interactive' | 'daemon';
  pid: number;
}

export interface UseProcessWrapperResult extends ProcessWrapperState {
  sendInput: (input: string) => void;
  sendResponse: (response: InteractionResponse) => void;
  disconnect: () => void;
}

interface SocketConnections {
  control: net.Socket | null;
  stdout: net.Socket | null;
  stderr: net.Socket | null;
  interactions: net.Socket | null;
  logs: net.Socket | null;
}

/**
 * External store for process wrapper state.
 * This allows React to properly subscribe to socket updates via useSyncExternalStore.
 */
function createProcessWrapperStore(runtimeDir: string) {
  let state: ProcessWrapperState = {
    connected: false,
    error: null,
    stdout: [],
    stderr: [],
    logs: [],
    currentInteraction: null,
    manifest: null,
  };

  const listeners = new Set<() => void>();
  const sockets: SocketConnections = {
    control: null,
    stdout: null,
    stderr: null,
    interactions: null,
    logs: null,
  };
  const buffers: Record<string, string> = {
    control: '',
    stdout: '',
    stderr: '',
    interactions: '',
    logs: '',
  };

  function setState(updater: (s: ProcessWrapperState) => ProcessWrapperState) {
    state = updater(state);
    listeners.forEach(listener => listener());
  }

  function getState() {
    return state;
  }

  function subscribe(listener: () => void) {
    listeners.add(listener);
    return () => listeners.delete(listener);
  }

  function processBuffer(channel: string, handler: (data: unknown) => void) {
    const buffer = buffers[channel];
    const lines = buffer.split('\n');
    buffers[channel] = lines.pop() || '';

    for (const line of lines) {
      if (line.trim()) {
        try {
          const parsed = JSON.parse(line);
          handler(parsed);
        } catch {
          handler(line);
        }
      }
    }
  }

  function connectSocket(
    socketPath: string,
    channel: keyof SocketConnections,
    onData: (data: unknown) => void
  ): Promise<net.Socket> {
    return new Promise((resolve, reject) => {
      const socket = net.createConnection(socketPath);

      socket.on('connect', () => {
        sockets[channel] = socket;
        resolve(socket);
      });

      socket.on('data', (chunk) => {
        buffers[channel] += chunk.toString();
        processBuffer(channel, onData);
      });

      socket.on('error', (err) => {
        reject(err);
      });

      socket.on('close', () => {
        sockets[channel] = null;
      });
    });
  }

  async function connect() {
    try {
      const manifestPath = path.join(runtimeDir, 'manifest.json');
      if (!fs.existsSync(manifestPath)) {
        throw new Error(`Manifest not found at ${manifestPath}`);
      }

      const manifestData = JSON.parse(fs.readFileSync(manifestPath, 'utf-8'));
      setState(s => ({ ...s, manifest: manifestData }));

      const controlPath = path.join(runtimeDir, 'control.sock');
      await connectSocket(controlPath, 'control', (data) => {
        if (data && typeof data === 'object' && 'type' in data && 'correlationId' in data) {
          const typeName = (data as { type: string }).type;
          if (typeName.includes('Request')) {
            setState(s => ({ ...s, currentInteraction: data as InteractionRequest }));
            return;
          }
        }
        if (typeof data === 'string') {
          setState(s => ({ ...s, stdout: [...s.stdout, data].slice(-100) }));
        } else {
          setState(s => ({ ...s, stdout: [...s.stdout, JSON.stringify(data)].slice(-100) }));
        }
      });

      const stderrPath = path.join(runtimeDir, 'stderr.sock');
      if (fs.existsSync(stderrPath)) {
        await connectSocket(stderrPath, 'stderr', (data) => {
          const text = typeof data === 'string' ? data : JSON.stringify(data);
          setState(s => ({ ...s, stderr: [...s.stderr, text] }));
        });
      }

      const logsPath = path.join(runtimeDir, 'logs.sock');
      if (fs.existsSync(logsPath)) {
        await connectSocket(logsPath, 'logs', (data) => {
          const text = typeof data === 'string' ? data : JSON.stringify(data);
          setState(s => ({ ...s, logs: [...s.logs, text] }));
        });
      }

      setState(s => ({ ...s, connected: true, error: null }));
    } catch (err) {
      setState(s => ({
        ...s,
        connected: false,
        error: err instanceof Error ? err.message : String(err),
      }));
    }
  }

  function sendInput(input: string) {
    const socket = sockets.control;
    if (socket && !socket.destroyed) {
      socket.write(input + '\n');
    }
  }

  function sendResponse(response: InteractionResponse) {
    const socket = sockets.control;
    if (socket && !socket.destroyed) {
      socket.write(JSON.stringify(response) + '\n');
      setState(s => ({ ...s, currentInteraction: null }));
    }
  }

  function disconnect() {
    Object.values(sockets).forEach(socket => {
      socket?.destroy();
    });
    setState(s => ({ ...s, connected: false }));
  }

  function cleanup() {
    Object.values(sockets).forEach(socket => {
      socket?.destroy();
    });
    listeners.clear();
  }

  return {
    getState,
    subscribe,
    connect,
    sendInput,
    sendResponse,
    disconnect,
    cleanup,
  };
}

/**
 * Hook for connecting to a Holon process-wrapper instance.
 * Uses useSyncExternalStore for proper React integration with socket updates.
 */
export function useProcessWrapper(runtimeDir: string): UseProcessWrapperResult {
  const storeRef = useRef<ReturnType<typeof createProcessWrapperStore> | null>(null);

  // Create store once
  if (!storeRef.current) {
    storeRef.current = createProcessWrapperStore(runtimeDir);
  }

  const store = storeRef.current;

  // Subscribe to store using useSyncExternalStore
  const state = useSyncExternalStore(store.subscribe, store.getState);

  // Connect on mount, cleanup on unmount
  useEffect(() => {
    store.connect();
    return () => store.cleanup();
  }, [store]);

  // Stable callbacks
  const sendInput = useCallback((input: string) => {
    store.sendInput(input);
  }, [store]);

  const sendResponse = useCallback((response: InteractionResponse) => {
    store.sendResponse(response);
  }, [store]);

  const disconnect = useCallback(() => {
    store.disconnect();
  }, [store]);

  return {
    ...state,
    sendInput,
    sendResponse,
    disconnect,
  };
}
