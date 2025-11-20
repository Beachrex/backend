# Git Hooks

This directory contains Git hooks for the project.

## Installation

To install the Git hooks, run:

```bash
./hooks/install.sh
```

## Available Hooks

### pre-commit

Runs all tests before allowing a commit, and if tests pass, automatically regenerates Swagger documentation.

**To skip the hook (not recommended):**
```bash
git commit --no-verify
```

## What happens on commit?

1. You run `git commit`
2. The pre-commit hook automatically runs `./vendor/bin/sail artisan test`
3. If any test fails ❌ - commit is aborted
4. If all tests pass ✅ - Swagger documentation is regenerated
5. If Swagger docs changed, they are automatically added to the commit
6. Commit proceeds with updated Swagger docs

This ensures that:
- Broken code is never committed to the repository
- Swagger documentation is always up-to-date with the latest API changes
- You never forget to regenerate Swagger docs after API changes

