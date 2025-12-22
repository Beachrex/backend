#!/bin/bash

# Fix Duplicate Beach Slugs - One Command for All Languages
# Usage: ./fix-duplicate-slugs.sh [--dry-run]

set -e

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🏖️  Beachrex - Fix Duplicate Beach Slugs"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check if we're in dry-run mode
DRY_RUN=""
if [ "$1" == "--dry-run" ]; then
    DRY_RUN="--dry-run"
    echo "⚠️  DRY RUN MODE - No changes will be made"
    echo ""
fi

# Step 1: Run migration (only if not dry-run)
if [ -z "$DRY_RUN" ]; then
    echo "📦 Step 1: Running migration..."
    php artisan migrate --force
    echo "✓ Migration complete"
    echo ""
fi

# Step 2: Fix duplicate slugs
echo "🔧 Step 2: Fixing duplicate slugs across all languages..."
php artisan beaches:fix-duplicate-slugs $DRY_RUN
echo ""

# Step 3: Clear cache (only if not dry-run)
if [ -z "$DRY_RUN" ]; then
    echo "🗑️  Step 3: Clearing cache..."
    php artisan cache:clear
    echo "✓ Cache cleared"
    echo ""
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if [ -z "$DRY_RUN" ]; then
    echo "✅ COMPLETE! All duplicate slugs have been fixed."
    echo ""
    echo "Next steps:"
    echo "  1. Test the API endpoints with new slugs"
    echo "  2. Frontend will automatically use new slugs from API"
    echo "  3. Old URLs will still work via redirect table"
else
    echo "✓ DRY RUN COMPLETE"
    echo ""
    echo "To apply changes, run:"
    echo "  ./fix-duplicate-slugs.sh"
fi
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

