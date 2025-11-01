#!/bin/bash

echo "Installing Git hooks..."

# Copy pre-commit hook
cp hooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit

echo "✅ Git hooks installed successfully!"
echo ""
echo "The pre-commit hook will now run tests before every commit."
echo "To skip the hook (not recommended), use: git commit --no-verify"

