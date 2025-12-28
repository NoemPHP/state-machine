# Persistence Layer Proposal

**Status**: Draft - Planning Phase
**Created**: 2025-12-28
**Target**: Future Release

---

## Overview

This directory contains the complete proposal for a **generic persistence layer** that enables serialization and rehydration of entire Region state machines.

The persistence layer will be implemented as an **optional feature** with no changes to core logic, providing:
- Pause/resume capabilities for long-running workflows
- Crash recovery with periodic snapshots
- Debugging support via state dumps
- Foundation for future database-backed Region collections

---

## Documents

### 📋 [persistence-layer.md](./persistence-layer.md) - Main Proposal

**Primary document** covering:
- Architecture and design decisions
- Core components (PersistenceFeature, PersistenceManager, backends)
- Serialization strategy for Region, Runtime, and ExtendedState
- Opt-in/opt-out mechanism via SerializationPolicy
- Implementation phases (MVP → Production)
- Use cases and extension points
- Open questions requiring decisions

**Read this first** for comprehensive understanding of the proposal.

---

### 🔬 [technical-research.md](./technical-research.md) - Implementation Patterns

**Research findings** from existing codebase patterns:

1. **Message Serialization Pattern** (Message.php)
   - JsonSerializable interface
   - Bidirectional marshalling (serialize/deserialize)
   - Type preservation via FQCN

2. **ReflectiveMessageSerialization** (trait)
   - Automatic serialization via reflection
   - Constructor parameter mapping
   - Performance considerations

3. **ExtendedState Context Storage** (Meta chain)
   - Context data in Mesh via ContextMetaType
   - Get/Set operations through Meta chain
   - Parent-child context inheritance

4. **Region Private State Access**
   - Reflection-based property extraction
   - newInstanceWithoutConstructor() pattern
   - Readonly property handling

5. **JsonSchemaFeature Extensibility**
   - YAML schema extension pattern
   - Feature configuration from YAML
   - LoaderChains integration

**Read this** to understand implementation approach based on proven codebase patterns.

---

### 💡 [usage-examples.md](./usage-examples.md) - Practical Examples

**Real-world usage scenarios**:

1. **Long-Running Order Processing**
   - eCommerce workflow with external payment
   - Pause at payment step, resume on webhook
   - Database-backed state storage

2. **Crash Recovery**
   - Data pipeline with periodic checkpoints
   - Resume from last snapshot on crash
   - Progress tracking

3. **Testing with Snapshots**
   - Reusable test fixtures
   - Multiple scenarios from same starting point
   - Avoiding expensive setup

4. **Distributed Job Queue**
   - Offload to background workers
   - Serialize, enqueue, resume elsewhere
   - Horizontal scaling

5. **Time-Travel Debugging**
   - Capture timeline of execution
   - Rewind to any point
   - Export/import debug sessions

6. **Multi-Tenant Workflow Management**
   - SaaS workflow storage
   - Per-tenant isolation
   - Query by state

**Read this** for practical implementation guidance and best practices.

---

## Quick Reference

### Key Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Implementation** | Optional Feature | Zero core changes, pure wrapper pattern |
| **Default Backend** | JSON | Human-readable, debuggable, no dependencies |
| **Closure Handling** | Skip/warn | Most production code should use named handlers |
| **State Definitions** | Require original | Simpler, forces machine versioning (can enhance later) |
| **Schema Versioning** | Version number + migrations | Simple for MVP, extensible for production |
| **Connected Regions** | Serialize entire tree | Required for context inheritance correctness |
| **Async Tasks** | Document idempotency | Generators can't serialize, operations must be resumable |

### Implementation Phases

| Phase | Goal | Effort | Priority |
|-------|------|--------|----------|
| **Phase 1** | Basic JSON serialization (Region + Runtime) | ~40h | P0 - MVP |
| **Phase 2** | Context serialization (ExtendedState) | ~30h | P0 - MVP |
| **Phase 3** | Advanced (Messages, custom MetaTypes) | ~50h | P1 - Production |
| **Phase 4** | Hardening (versioning, validation) | ~40h | P1 - Production |
| **Phase 5** | Extensions (DB backend, time-travel) | TBD | P2 - Future |

**Total MVP (P0)**: ~70 hours / 2 weeks
**Total Production (P0+P1)**: ~160 hours / 4 weeks

### Required Dependencies

| Dependency | Version | Purpose | Required? |
|------------|---------|---------|-----------|
| PHP | 8.4+ | Core requirement | Yes |
| ext-json | * | JSON serialization | Yes |
| ext-reflection | * | Private property access | Yes |
| ext-igbinary | * | Binary backend (optional) | No |
| ext-msgpack | * | MessagePack backend (optional) | No |
| opis/closure | ^4.0 | Closure serialization (optional) | No |

### Extension Points

**Future enhancements supported by architecture**:

- ✅ **DatabaseBackend** - Store snapshots in relational DB
- ✅ **RedisBackend** - Distributed state with TTL
- ✅ **BinaryBackend** - Compact snapshots (igbinary/msgpack)
- ✅ **IncrementalBackend** - Delta compression for large states
- ✅ **TimeravelBackend** - Snapshot history with rewind capability
- ✅ **Region Collections** - Load/manage sets of workflows from DB
- ✅ **Distributed Sync** - CRDTs for multi-node state synchronization

---

## Open Questions

These require decision before implementation:

### 1. Closure Handling

**Question**: How to handle closures in RuntimeConfig and handlers?

**Options**:
- A. Skip entirely (warn, set to null) ← **Recommended for MVP**
- B. Require Opis/Closure (optional dependency)
- C. Serialize source code (fragile)
- D. Force named functions only

**Decision needed by**: Phase 1 completion

---

### 2. State Definition Storage

**Question**: Should snapshots include machine definitions or require original YAML?

**Options**:
- A. Runtime state only (require machine definition) ← **Recommended for MVP**
- B. Full self-contained snapshots (include all logic)

**Trade-offs**:
- A: Simpler, smaller, forces versioning
- B: True "pause anywhere", more complex

**Decision needed by**: Phase 2 start

---

### 3. Async Task Handling

**Question**: How to handle in-flight coroutines/generators?

**Options**:
- A. Don't support (throw error)
- B. Serialize generator state (impossible?)
- C. Serialize task queue only
- D. Require idempotent operations ← **Recommended**

**Guidance**: Document that async operations must be resumable (checkpoint-based)

**Decision needed by**: Phase 3 start

---

## Next Steps

1. **Review & Feedback**
   - Share with maintainers
   - Gather feedback on architecture
   - Resolve open questions

2. **Create Specifications**
   - Follow spec-driven development methodology
   - Create YAML specs for Phase 1 components
   - Define acceptance criteria

3. **Implementation**
   - Phase 1: Basic serialization (2 weeks)
   - Phase 2: Context support (1.5 weeks)
   - Phase 3: Advanced features (2.5 weeks)
   - Phase 4: Production hardening (2 weeks)

4. **Documentation**
   - Feature documentation in src/Feature/Persistence/CLAUDE.md
   - Update main README with persistence examples
   - API reference documentation

5. **Testing**
   - Unit tests (serialization/deserialization)
   - Integration tests (end-to-end workflows)
   - Performance benchmarks (snapshot size, speed)
   - Real-world scenario tests

---

## Contributing

When contributing to this proposal:

1. ✅ Follow spec-driven development methodology
2. ✅ No changes to core (Region, Runtime, RegionBuilder)
3. ✅ Pure Feature pattern implementation
4. ✅ Maintain backward compatibility
5. ✅ Document all design decisions
6. ✅ Add tests for all functionality

---

## Related Resources

### Internal
- [Runtime Proposal](../runtime/Runtime.md) - Runtime architecture
- [Abilities API](../abilities-api/abilities-api.md) - Similar feature pattern
- [ExtendedState CLAUDE.md](/src/Feature/ExtendedState/CLAUDE.md) - Context management

### External
- [JSON Schema](https://json-schema.org/) - Schema validation patterns
- [Opis Closure](https://github.com/opis/closure) - Closure serialization
- [igbinary](https://github.com/igbinary/igbinary) - Binary serialization
- [MessagePack](https://msgpack.org/) - Efficient binary format

---

## License

Part of Noem State Machine project - same license applies.

---

**Last Updated**: 2025-12-28
**Status**: Awaiting review and feedback
**Contact**: Open GitHub issue for questions/discussion
