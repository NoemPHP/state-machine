# Active Proposals

This directory contains **proposals for features that are not yet implemented**.

## Current Proposals

| File | Status | Description |
|------|--------|-------------|
| `machine-agent.md` | Draft | Autonomous machine generation from natural language |
| `process-wrapper.md` | Draft | Unix-style process wrapper for Holons (stdin/stdout/stderr) |
| `model-selection-by-capability.md` | Draft | Capability-based AI model selection |

## Proposal Lifecycle

```
Draft → Review → Approved → Spec Created → Implementation
                                ↓
                      Moved to /docs/implemented/
```

## Contributing

To propose a new feature:

1. Create a markdown file in this directory
2. Include: problem statement, proposed solution, API design, alternatives considered
3. Submit for review
4. Once approved, create specs in `/specs/`
5. After implementation, move documentation to `/docs/implemented/`

## See Also

- `/docs/implemented/` - Documentation for implemented features
- `/docs/drafts/` - Work-in-progress technical documentation
- `/specs/` - Formal specifications (source of truth)
