# Approved Designs

**Status**: Index
**Updated**: 2026-01-16

## Overview

This directory contains **approved design records** that are ready for specification creation and implementation.

## Lifecycle

```
design-records/drafts/     User approves design
        ↓                           ↓
design-records/greenlit/   spec-planner creates YAML specs
        ↓                           ↓
design-records/implemented/  Feature complete
```

## Current Approved Designs

| Directory | Description | Next Step |
|-----------|-------------|-----------|
| *(empty)* | No designs currently awaiting implementation | - |

## Moving Documents Here

When a design is approved:

1. Update the document's `**Status**:` field to `Approved`
2. Move the folder: `mv design-records/drafts/{topic}/ design-records/greenlit/`
3. Update any internal links if necessary

## Next Steps for Approved Designs

1. Use `spec-planner` agent to create YAML specifications
2. Get user approval on specs
3. Use `core-development-expert` to implement
4. Move to `implemented/` when complete

## See Also

- [drafts/](../drafts/) - Work-in-progress designs
- [implemented/](../implemented/) - Completed features
