# Plugin Auto-Update Setup Guide

This guide will show you how to set up automatic updates for your WooCommerce Abandoned Cart Tracker plugin using GitHub releases.

## How It Works

1. **Push code changes** to your GitHub repository
2. **Create a release** with a new version tag (e.g., v1.0.1)
3. **WordPress automatically checks** for updates and shows update notification
4. **Users can update** directly from their WordPress admin

## Setup Steps

### 1. Create GitHub Repository

```bash
# Navigate to your plugin directory
cd /home/razvan/projects/wp-plugins/abandoned-cart

# Initialize git repository
git init

# Add all files
git add .

# Make first commit
git commit -m "Initial commit - WooCommerce Abandoned Cart Tracker v1.0.0"

# Add GitHub remote (replace with your repository URL)
git remote add origin https://github.com/YOUR-USERNAME/abandoned-cart.git

# Push to GitHub
git push -u origin main
```

### 2. Configure Plugin Updater

Edit `abandoned-cart-tracker.php` and replace these values:

```php
new ACT_Plugin_Updater(
    __FILE__,
    'YOUR-GITHUB-USERNAME',      // Your actual GitHub username
    'abandoned-cart',    // Your repository name
    'ghp_your_token_here'       // Optional: Personal Access Token for private repos
);
```

### 3. Version Update Workflow

When you make changes to your plugin:

#### Step 1: Update Version Number
Edit the main plugin file header:
```php
/**
 * Version: 1.0.1  // Increment this number
 */
```

#### Step 2: Commit and Push Changes
```bash
git add .
git commit -m "Fix CSV export column alignment - v1.0.1"
git push origin main
```

#### Step 3: Create GitHub Release
```bash
# Create and push a new tag
git tag v1.0.1
git push origin v1.0.1
```

Or create a release through GitHub web interface:
1. Go to your repository on GitHub
2. Click "Releases" → "Create a new release"
3. Set tag version: `v1.0.1`
4. Set release title: `Version 1.0.1`
5. Add release notes describing changes
6. Click "Publish release"

### 4. WordPress Update Process

Once you create a release:

1. **WordPress checks for updates** (every 12 hours by default)
2. **Update notification appears** in WordPress admin
3. **Users can click "Update"** to get the new version
4. **Plugin updates automatically** from GitHub

## Alternative Options

### Option A: WordPress.org Repository (Free)

Submit your plugin to the WordPress.org repository:

**Pros:**
- Automatic updates through WordPress
- Better discovery and trust
- Free hosting

**Cons:**
- Review process required
- Must follow WordPress.org guidelines
- Code must be GPL licensed

**Steps:**
1. Submit to https://wordpress.org/plugins/developers/add/
2. Wait for review (can take weeks)
3. Updates push automatically

### Option B: Premium Plugin Marketplace

Use services like:
- **Easy Digital Downloads** with Software Licensing
- **WP Updates** service
- **Kernl.us** (plugin update service)

### Option C: Custom Update Server

Create your own update server:

```php
// Custom update server endpoint
add_action('plugins_api', 'custom_plugin_api_call', 10, 3);
add_filter('pre_set_site_transient_update_plugins', 'custom_check_for_plugin_update');
```

## GitHub Release Automation

### Using GitHub Actions

Create `.github/workflows/release.yml`:

```yaml
name: Create Release
on:
  push:
    tags:
      - 'v*'

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Create Release
        uses: actions/create-release@v1
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        with:
          tag_name: ${{ github.ref }}
          release_name: Release ${{ github.ref }}
          draft: false
          prerelease: false
```

### Automated Version Bumping

Create a script to automate version updates:

```bash
#!/bin/bash
# bump-version.sh

NEW_VERSION=$1
if [ -z "$NEW_VERSION" ]; then
    echo "Usage: ./bump-version.sh 1.0.1"
    exit 1
fi

# Update plugin header
sed -i "s/Version: .*/Version: $NEW_VERSION/" abandoned-cart-tracker.php

# Update constant
sed -i "s/define('ACT_VERSION', '.*');/define('ACT_VERSION', '$NEW_VERSION');/" abandoned-cart-tracker.php

# Commit and tag
git add .
git commit -m "Bump version to $NEW_VERSION"
git tag "v$NEW_VERSION"
git push origin main
git push origin "v$NEW_VERSION"

echo "Version $NEW_VERSION released!"
```

Usage:
```bash
chmod +x bump-version.sh
./bump-version.sh 1.0.1
```

## Testing Updates

1. **Install plugin** on a test WordPress site
2. **Lower the version** number in the plugin file
3. **Create a new release** with higher version
4. **Check WordPress admin** for update notification
5. **Test the update process**

## Troubleshooting

### Updates Not Showing
- Check GitHub API rate limits
- Verify repository is public or token is correct
- Check WordPress transients: `delete_site_transient('update_plugins')`

### Download Fails
- Ensure release ZIP contains the plugin folder
- Check GitHub release assets are properly generated
- Verify download URL is accessible

### Plugin Breaks After Update
- Test updates on staging sites first
- Include proper activation/deactivation hooks
- Handle database schema changes carefully

## Security Considerations

- **Use HTTPS** for all update checks
- **Verify checksums** if possible
- **Sanitize all input** from update server
- **Use access tokens** for private repositories
- **Rate limit** update checks

## Best Practices

1. **Semantic Versioning**: Use MAJOR.MINOR.PATCH format
2. **Release Notes**: Always include detailed changelog
3. **Testing**: Test updates on staging before release
4. **Backwards Compatibility**: Maintain compatibility when possible
5. **Database Migrations**: Handle schema changes gracefully

## Example Release Workflow

```bash
# 1. Make changes
vim abandoned-cart-tracker.php

# 2. Test changes
# ... testing ...

# 3. Update version and release
./bump-version.sh 1.0.2

# 4. WordPress sites will see update notification within 12 hours
```

That's it! Your plugin now has professional automatic updates just like plugins from the WordPress repository.
