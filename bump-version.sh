#!/bin/bash

# WooCommerce Abandoned Cart Tracker - Version Bump Script
# Usage: ./bump-version.sh 1.0.1

set -e

NEW_VERSION=$1
if [ -z "$NEW_VERSION" ]; then
    echo "❌ Error: Please specify a version number"
    echo "Usage: ./bump-version.sh 1.0.1"
    exit 1
fi

echo "🚀 Bumping version to $NEW_VERSION..."

# Update plugin header version
echo "📝 Updating plugin header..."
sed -i "s/Version: .*/Version: $NEW_VERSION/" abandoned-cart-tracker.php

# Update version constant
echo "📝 Updating version constant..."
sed -i "s/define('ACT_VERSION', '.*');/define('ACT_VERSION', '$NEW_VERSION');/" abandoned-cart-tracker.php

# Check if there are any changes to commit
if git diff --quiet; then
    echo "⚠️  Warning: No changes detected. Make sure you've made code changes before bumping version."
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "❌ Aborted."
        exit 1
    fi
fi

# Show what will be committed
echo "📋 Changes to be committed:"
git diff --name-only

# Commit changes
echo "💾 Committing changes..."
git add .
git commit -m "Release version $NEW_VERSION

- Updated plugin version to $NEW_VERSION
- Ready for release"

# Create and push tag
echo "🏷️  Creating tag v$NEW_VERSION..."
git tag "v$NEW_VERSION"

# Push everything
echo "📤 Pushing to repository..."
git push origin main
git push origin "v$NEW_VERSION"

echo "✅ Version $NEW_VERSION has been released!"
echo ""
echo "🎯 Next steps:"
echo "1. Go to GitHub and create a release for tag v$NEW_VERSION"
echo "2. Add release notes describing the changes"
echo "3. WordPress sites will check for updates within 12 hours"
echo ""
echo "🔗 GitHub releases: https://github.com/razvanmt/abandoned-cart/releases"
