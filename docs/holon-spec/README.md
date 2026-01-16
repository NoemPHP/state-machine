# Holon YAML Spec (Compact)

Machine-readable specification for AI coding agents generating Holon YAML files.

## Files

| File | Purpose | Lines |
|------|---------|-------|
| `schema.json` | JSON Schema for validation | ~90 |
| `example.yaml` | Complete annotated example | ~80 |
| `constraints.md` | Critical rules & tables | ~60 |

## Usage

**For validation:** Parse against `schema.json`

**For generation:** Reference `example.yaml` for structure, `constraints.md` for rules

## vs machines/machine-generator/holon-spec.yaml

That file is verbose human documentation (~1100 lines). These files are compact AI-friendly specs (~230 lines total).
