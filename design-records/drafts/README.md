# Draft Designs

**Status**: Index
**Updated**: 2026-01-16

## Overview

This directory contains **work-in-progress design records**. These are proposals that are being developed, researched, or awaiting user review.

## Lifecycle

```
User requests new feature
        ↓
solution-architect creates design
        ↓
design-records/drafts/   ← You are here
        ↓
User approves design
        ↓
design-records/greenlit/
        ↓
spec-planner creates specs → core-development-expert implements
        ↓
design-records/implemented/
```

## Current Drafts

| Directory | Description | Status |
|-----------|-------------|--------|
| [async-resolvers/](./async-resolvers/) | Unimplemented async functionality (lazy resolvers, event-driven helpers) | Awaiting prioritization |
| [persistence/](./persistence/) | State machine serialization and persistence layer | Awaiting review |
| [spec-standard-enhancement/](./spec-standard-enhancement/) | Enhanced spec format with test execution config | Awaiting review |

## Creating New Drafts

New design records should be created by the `solution-architect` agent:

1. User requests a new feature
2. `solution-architect` researches the codebase
3. Creates design record in `design-records/drafts/{topic}/`
4. Primary document: `{topic}.md`
5. For multi-document designs: add `README.md` as index

## Document Format

All drafts must follow the standard format:

```markdown
# Feature Name

**Status**: Draft
**Created**: YYYY-MM-DD

## Overview
...
```

## Moving to Greenlit

When user approves a design:

1. Update `**Status**:` to `Approved`
2. Move folder: `mv design-records/drafts/{topic}/ design-records/greenlit/`
3. Update internal links if necessary

## See Also

- [greenlit/](../greenlit/) - Approved, awaiting implementation
- [implemented/](../implemented/) - Completed features
