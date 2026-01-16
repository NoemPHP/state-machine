#!/usr/bin/env node
import React from 'react';
import { render } from 'ink';
import meow from 'meow';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { App } from './App.js';

const cli = meow(`
  Usage
    $ holon-cli <runtime-dir>
    $ holon-cli --pid <pid>

  Options
    --pid, -p    Connect by PID (looks for /tmp/holon-<pid>)
    --help       Show this help message
    --version    Show version

  Examples
    $ holon-cli /tmp/holon-12345
    $ holon-cli -p 12345

  Description
    Interactive CLI client for Holon process-wrapper.
    Connects to a running holon process and handles interactions.
`, {
  importMeta: import.meta,
  flags: {
    pid: {
      type: 'string',
      shortFlag: 'p',
    },
  },
});

function findRuntimeDir(): string | null {
  // Check for PID flag
  if (cli.flags.pid) {
    const dir = `/tmp/holon-${cli.flags.pid}`;
    if (fs.existsSync(dir)) {
      return dir;
    }
    console.error(`Error: Runtime directory not found: ${dir}`);
    process.exit(1);
  }

  // Check for positional argument
  if (cli.input.length > 0) {
    const dir = cli.input[0];
    if (fs.existsSync(dir)) {
      return dir;
    }
    console.error(`Error: Runtime directory not found: ${dir}`);
    process.exit(1);
  }

  // Auto-detect: find most recent holon runtime dir
  const tmpDir = '/tmp';
  try {
    const dirs = fs.readdirSync(tmpDir)
      .filter(name => name.startsWith('holon-'))
      .map(name => ({
        name,
        path: path.join(tmpDir, name),
        stat: fs.statSync(path.join(tmpDir, name)),
      }))
      .filter(d => d.stat.isDirectory())
      .sort((a, b) => b.stat.mtimeMs - a.stat.mtimeMs);

    if (dirs.length > 0) {
      return dirs[0].path;
    }
  } catch {
    // Ignore errors
  }

  return null;
}

const runtimeDir = findRuntimeDir();

if (!runtimeDir) {
  console.error('Error: No runtime directory specified and none found in /tmp');
  console.error('');
  console.error('Start a holon process first:');
  console.error('  php run.php machines/process-wrapper/holon.yml -d your-machine.yml');
  console.error('');
  console.error('Then connect:');
  console.error('  holon-cli -p <pid>');
  console.error('  holon-cli /tmp/holon-<pid>');
  process.exit(1);
}

// Verify manifest exists
const manifestPath = path.join(runtimeDir, 'manifest.json');
if (!fs.existsSync(manifestPath)) {
  console.error(`Error: manifest.json not found in ${runtimeDir}`);
  console.error('Is the holon process still running?');
  process.exit(1);
}

// Enter alternate screen buffer (prevents ghost lines)
process.stdout.write('\x1b[?1049h'); // Enter alt buffer
process.stdout.write('\x1b[H');      // Move cursor to home
process.stdout.write('\x1b[2J');     // Clear screen

// Cleanup on exit
const cleanup = () => {
  process.stdout.write('\x1b[?1049l'); // Exit alt buffer
};
process.on('exit', cleanup);
process.on('SIGINT', () => { cleanup(); process.exit(0); });
process.on('SIGTERM', () => { cleanup(); process.exit(0); });

// Render the app
const { waitUntilExit } = render(<App runtimeDir={runtimeDir} />);

waitUntilExit().then(() => {
  cleanup();
  process.exit(0);
});
