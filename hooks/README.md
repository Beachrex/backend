# Git Hooks

This directory contains Git hooks for the project.

## Installation

To install the Git hooks, run:

```bash
./hooks/install.sh
```

## Available Hooks

### pre-commit

Runs all tests before allowing a commit. If any tests fail, the commit will be aborted.

**To skip the hook (not recommended):**
```bash
git commit --no-verify
```

## What happens on commit?

1. You run `git commit`
2. The pre-commit hook automatically runs `./vendor/bin/sail artisan test`
3. If all tests pass ✅ - commit proceeds
4. If any test fails ❌ - commit is aborted

This ensures that broken code is never committed to the repository.

