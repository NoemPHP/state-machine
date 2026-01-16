# Implemented Features - Reference Documentation

This directory contains **design documentation for features that have been implemented**.

These documents serve as architectural reference material. For current usage documentation, see the respective feature's `CLAUDE.md` file in `src/Feature/`.

## Contents

| Directory/File | Feature | Spec | Implementation |
|----------------|---------|------|----------------|
| `agentic-interactions/` | InteractionFeature | `specs/features/interaction.yaml` | `src/Feature/Interaction/` |
| `presentation-feature.md` | PresentationFeature | `specs/features/presentation.yaml` | `src/Feature/Presentation/` |
| `abilities-api/` | AbilitiesFeature | `specs/features/abilities.yaml` | `src/Feature/Abilities/` |
| `ai-enhancements/` | AiFeature, AgenticFeature | `specs/features/ai.yaml`, `specs/features/agentic.yaml` | `src/Feature/Ai/`, `src/Feature/Agentic/` |
| `runtime/` | Runtime Architecture | `specs/core/` | `src/` (core) |

## Usage

For up-to-date usage instructions, prefer:

1. **Feature CLAUDE.md files**: `src/Feature/{Name}/CLAUDE.md`
2. **Spec files**: `specs/features/{name}.yaml`
3. **Tests**: `tests/PHPUnit/Unit/Feature/{Name}/`

These documents are preserved for:
- Historical context on design decisions
- Architectural rationale
- Implementation planning reference

## See Also

- `/docs/proposals/` - Active proposals not yet implemented
- `/docs/drafts/` - Work-in-progress documentation
