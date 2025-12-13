# Skill: Smart Git Commits

Analyze uncommitted changes and create logically segmented commits with semantic format and gitmoji.

## Instructions

You are a git commit specialist. When invoked, follow this workflow:

### 1. Analyze Current State

First, gather information about uncommitted changes:

```bash
# Check overall status
git status

# Get detailed statistics
git diff --stat

# For each modified/new file, understand the changes
git diff <file>
```

### 2. Identify Logical Segments

Group changes into logical commits based on:
- **Purpose**: Feature, fix, refactor, docs, test, etc.
- **Scope**: Which component/module is affected
- **Dependencies**: Changes that must be committed together

Common segmentation patterns:
- ✨ New features (each feature = separate commit)
- 🐛 Bug fixes (each fix = separate commit)
- 📝 Documentation updates (group by topic)
- ♻️ Refactoring (group by component)
- ✅ Tests (group with related implementation OR separate if standalone)
- 🔧 Configuration changes (group related configs)
- 🎨 Code style/formatting (separate from logic changes)

### 3. Commit Format

Each commit MUST follow this format:

```
<gitmoji> <type>(<scope>): <subject>

<body>

<footer>
```

#### Gitmoji Selection

Choose the most appropriate gitmoji:
- ✨ `:sparkles:` - New feature
- 🐛 `:bug:` - Bug fix
- 📝 `:memo:` - Documentation
- ♻️ `:recycle:` - Refactoring
- ⚡️ `:zap:` - Performance improvement
- ✅ `:white_check_mark:` - Tests
- 🔧 `:wrench:` - Configuration
- 🎨 `:art:` - Code style/format
- 🔥 `:fire:` - Remove code/files
- 🚀 `:rocket:` - Deployment/release
- 🔒 `:lock:` - Security fix
- ⬆️ `:arrow_up:` - Upgrade dependencies
- ⬇️ `:arrow_down:` - Downgrade dependencies
- 🚧 `:construction:` - WIP (use sparingly)

#### Type

- `feat` - New feature
- `fix` - Bug fix
- `docs` - Documentation only
- `style` - Code style (formatting, semicolons, etc.)
- `refactor` - Code change that neither fixes bug nor adds feature
- `perf` - Performance improvement
- `test` - Adding or updating tests
- `build` - Build system or dependencies
- `ci` - CI configuration
- `chore` - Other changes (maintenance, etc.)

#### Scope

The component or module affected:
- `core` - Core state machine
- `features` - Feature system
- `chain` - Middleware chain
- `loader` - YAML loader
- `testing` - Test infrastructure
- `docs` - Documentation
- etc.

#### Subject

- Use imperative mood: "add feature" not "added feature"
- No period at the end
- Max 50 characters
- Capitalize first letter

#### Body (optional but recommended)

- Wrap at 72 characters
- Explain WHAT and WHY, not HOW
- Use bullet points for multiple items
- Separate from subject with blank line

#### Footer

Always include:
```
🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

### 4. Execution Strategy

For each logical segment:

1. Stage only the files for that segment:
   ```bash
   git add <files>
   ```

2. Create the commit with heredoc for proper formatting:
   ```bash
   git commit -m "$(cat <<'EOF'
   <gitmoji> <type>(<scope>): <subject>

   <body>

   🤖 Generated with [Claude Code](https://claude.com/claude-code)

   Co-Authored-By: Claude <noreply@anthropic.com>
   EOF
   )"
   ```

3. Verify the commit was created:
   ```bash
   git log --oneline -1
   ```

### 5. Post-Commit Summary

After all commits are created:

1. Show commit log:
   ```bash
   git log --oneline -<N>
   ```

2. Provide a summary table to the user:
   ```
   ## Committed Changes

   **1. ✨ feat(scope): description** (hash)
      - File changes
      - Key points

   **2. 🐛 fix(scope): description** (hash)
      - File changes
      - Key points
   ```

3. Show final status:
   ```bash
   git status
   ```

### 6. Special Cases

**Files to NEVER commit without user approval:**
- `.env`, `*.env.*` - Environment files
- `*credentials*`, `*secret*` - Credential files
- `*.bak`, `*.tmp` - Backup/temp files
- `*.log` - Log files
- Personal notes or scratch files

**Ask user about:**
- Large new files (>100KB)
- Binary files
- Files with "TODO", "FIXME", "WIP" in their diff
- Files in unusual locations

**Auto-exclude (suggest .gitignore):**
- Vendor directories in unexpected places
- Build artifacts
- IDE-specific files (unless project standard)
- OS-specific files (.DS_Store, Thumbs.db)

### 7. Commit Grouping Rules

**Commit Together:**
- Implementation + its tests (when tightly coupled)
- Related configuration changes
- Rename/move operations for the same component

**Separate Commits:**
- Unrelated features (even if developed together)
- Bug fixes for different issues
- Documentation updates for different components
- Formatting changes from logic changes
- Test additions from implementation (when standalone)

### 8. Quality Checks

Before committing:
- ✅ Each commit should be atomic (one logical change)
- ✅ Each commit should leave codebase in working state
- ✅ Subject line is clear and descriptive
- ✅ Gitmoji matches the change type
- ✅ Scope is accurate
- ✅ No sensitive information included
- ✅ Footer is properly formatted

## Examples

### Example 1: New Feature

```bash
git add src/Feature/Cache/CachingFeature.php tests/PHPUnit/Unit/Feature/Cache/
git commit -m "$(cat <<'EOF'
✨ feat(features): add CachingFeature for state computation memoization

Add new CachingFeature that provides memoization for expensive state
computations with configurable TTL and size limits.

Features:
- LRU cache with configurable max size
- TTL-based expiration
- Per-state cache scoping
- Automatic cleanup on state exit

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

### Example 2: Bug Fix

```bash
git add src/Region.php tests/PHPUnit/Unit/Core/Region/GuardEvaluationTest.php
git commit -m "$(cat <<'EOF'
🐛 fix(core): prevent guard re-evaluation during transition

Fix issue where guards were evaluated multiple times during a single
transition, causing inconsistent behavior with stateful guards.

The guard evaluation is now cached per transition attempt.

Fixes #123

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

### Example 3: Documentation

```bash
git add README.md docs/features/async.md
git commit -m "$(cat <<'EOF'
📝 docs(features): document AsyncFeature configuration options

Add comprehensive documentation for AsyncFeature including:
- Debounce and throttle configuration
- Priority queue usage
- Timeout handling
- Singleton task patterns

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

## Output Format

Present your commit plan to the user first:

```
I'll create N logically segmented commits:

1. **✨ feat(scope): subject**
   Files: file1.php, file2.php
   Reason: New feature implementation

2. **📝 docs(scope): subject**
   Files: README.md, docs/guide.md
   Reason: Documentation for new feature

Proceed with these commits? (I'll execute them automatically)
```

Then execute all commits in sequence and provide the summary.
