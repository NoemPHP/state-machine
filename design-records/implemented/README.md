# Implemented Features

**Status**: Index
**Updated**: 2026-01-16

## Overview

This directory contains **design records for features that have been implemented**. These documents serve as architectural reference material.

For current usage documentation, see the respective feature's `CLAUDE.md` file in `src/Feature/`.

## Contents

| Directory | Feature | Spec | Implementation |
|-----------|---------|------|----------------|
| [abilities-api/](./abilities-api/) | AbilitiesFeature | `specs/features/abilities.yaml` | `src/Feature/Abilities/` |
| [agentic-interactions/](./agentic-interactions/) | InteractionFeature | `specs/features/interaction.yaml` | `src/Feature/Interaction/` |
| [ai-enhancements/](./ai-enhancements/) | AiFeature, AgenticFeature | `specs/features/ai.yaml` | `src/Feature/Ai/`, `src/Feature/Agentic/` |
| [context-broadcast/](./context-broadcast/) | ContextBroadcastFeature | `specs/features/context-broadcast.yaml` | `src/Feature/ContextBroadcast/` |
| [model-selection-by-capability/](./model-selection-by-capability/) | AI Model Selection | - | `src/Feature/Ai/` |
| [presentation/](./presentation/) | PresentationFeature | `specs/features/presentation.yaml` | `src/Feature/Presentation/` |
| [runtime/](./runtime/) | Runtime Architecture | `specs/core/runtime.yaml` | `src/` (core) |

## Document Structure

Each implemented feature directory contains:

- **Primary document** - Core design and architecture
- **Companion documents** (optional) - Examples, deep-dives, analysis

## Usage

For up-to-date usage instructions, prefer:

1. **Feature CLAUDE.md files**: `src/Feature/{Name}/CLAUDE.md`
2. **Spec files**: `specs/features/{name}.yaml`
3. **Tests**: `tests/PHPUnit/Unit/Feature/{Name}/`

These design records are preserved for:

- Historical context on design decisions
- Architectural rationale
- Implementation planning reference

## See Also

- [drafts/](../drafts/) - Work-in-progress designs
- [greenlit/](../greenlit/) - Approved, awaiting implementation
