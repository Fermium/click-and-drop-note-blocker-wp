#!/bin/bash

# Royal Mail Note Blocker - Local Build Script
# This script creates a distributable zip file of the WordPress plugin

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

print_status "Building Royal Mail Note Blocker plugin..."

# Check if required files exist
REQUIRED_FILES=("royal-mail-note-blocker.php" "readme.txt" "LICENSE" "uninstall.php" "index.php")
for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        print_error "Required file '$file' not found!"
        exit 1
    fi
done

print_success "All required files found"

# Extract version from plugin file
VERSION=$(grep "Version:" royal-mail-note-blocker.php | sed 's/.*Version: *\([0-9.]*\).*/\1/')
if [ -z "$VERSION" ]; then
    print_error "Could not extract version from plugin file"
    exit 1
fi

# Verify version consistency across files
CONSTANT_VERSION=$(grep "define('RMNB_VERSION'" royal-mail-note-blocker.php | sed "s/.*'\\([0-9.]*\\)'.*/\\1/")
README_VERSION=$(grep "Stable tag:" readme.txt | sed 's/.*Stable tag: *\([0-9.]*\).*/\1/')

if [ "$VERSION" != "$CONSTANT_VERSION" ] || [ "$VERSION" != "$README_VERSION" ]; then
    print_warning "Version inconsistency detected:"
    print_warning "  Plugin header: $VERSION"
    print_warning "  Plugin constant: $CONSTANT_VERSION"
    print_warning "  Readme stable tag: $README_VERSION"
    print_warning "Consider running the version sync workflow or update manually"
fi

print_status "Plugin version: $VERSION"

# Create build directory
BUILD_DIR="build"
PLUGIN_DIR="$BUILD_DIR/royal-mail-note-blocker"
ZIP_NAME="royal-mail-note-blocker-v$VERSION.zip"

print_status "Creating build directory..."
rm -rf "$BUILD_DIR"
mkdir -p "$PLUGIN_DIR"

# Copy files to build directory
print_status "Copying plugin files..."
cp royal-mail-note-blocker.php "$PLUGIN_DIR/"
cp readme.txt "$PLUGIN_DIR/"
cp LICENSE "$PLUGIN_DIR/"
cp uninstall.php "$PLUGIN_DIR/"
cp index.php "$PLUGIN_DIR/"

# Create installation readme
print_status "Creating installation readme..."
cat > "$PLUGIN_DIR/INSTALL.txt" << EOF
Royal Mail Note Blocker v$VERSION
==================================

Installation Instructions:
1. Upload this folder to /wp-content/plugins/
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under Settings > Royal Mail Note Blocker

Requirements:
- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

For more information visit:
https://github.com/Fermium/click-and-drop-note-blocker-wp

Support:
Create an issue at the GitHub repository for support.
EOF

# Validate PHP syntax
print_status "Validating PHP syntax..."
if ! php -l "$PLUGIN_DIR/royal-mail-note-blocker.php" > /dev/null; then
    print_error "PHP syntax error in main plugin file"
    exit 1
fi

if ! php -l "$PLUGIN_DIR/uninstall.php" > /dev/null; then
    print_error "PHP syntax error in uninstall file"
    exit 1
fi

print_success "PHP syntax validation passed"

# Create zip file
print_status "Creating zip file: $ZIP_NAME"
cd "$BUILD_DIR"
zip -r "../$ZIP_NAME" royal-mail-note-blocker/ > /dev/null
cd ..

# Generate checksum
print_status "Generating SHA256 checksum..."
if command -v sha256sum > /dev/null; then
    CHECKSUM=$(sha256sum "$ZIP_NAME" | cut -d' ' -f1)
elif command -v shasum > /dev/null; then
    CHECKSUM=$(shasum -a 256 "$ZIP_NAME" | cut -d' ' -f1)
else
    print_warning "SHA256 command not found, skipping checksum"
    CHECKSUM="N/A"
fi

# Display results
print_success "Build completed successfully!"
echo
echo "═══════════════════════════════════════"
echo "  BUILD SUMMARY"
echo "═══════════════════════════════════════"
echo "Plugin Version: $VERSION"
echo "Output File: $ZIP_NAME"
echo "File Size: $(du -h "$ZIP_NAME" | cut -f1)"
if [ "$CHECKSUM" != "N/A" ]; then
    echo "SHA256: $CHECKSUM"
fi
echo "═══════════════════════════════════════"
echo

print_status "The plugin is ready for distribution!"
print_status "Upload $ZIP_NAME to WordPress via Plugins > Add New > Upload Plugin"

# Clean up build directory
rm -rf "$BUILD_DIR"

print_success "Build process completed!"
