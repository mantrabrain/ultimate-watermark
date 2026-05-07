#!/bin/bash

# Ultimate Watermark - Production Build Script
# Generates production-ready zip file in builder folder

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Plugin information
PLUGIN_SLUG="ultimate-watermark"
PLUGIN_NAME="Ultimate Watermark"
BUILD_DIR="builder"
TEMP_DIR="$BUILD_DIR/temp"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Building ${PLUGIN_NAME}${NC}"
echo -e "${GREEN}  Production-Ready Package${NC}"
echo -e "${GREEN}========================================${NC}"

# Get plugin version from main file
VERSION=$(grep "^ \* Version:" ultimate-watermark.php | sed 's/.*Version: *//' | tr -d '\r')
if [ -z "$VERSION" ]; then
    echo -e "${RED}✗ Could not determine plugin version from ultimate-watermark.php${NC}"
    exit 1
fi
echo -e "${BLUE}Version: ${VERSION}${NC}"

# Check if composer dependencies are installed
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}⚠ Composer vendor directory not found. Running composer install...${NC}"
    if command -v composer &> /dev/null; then
        composer install --no-dev --optimize-autoloader --quiet
        echo -e "${GREEN}✓ Dependencies installed${NC}"
    else
        echo -e "${RED}✗ Composer is not installed. Please run 'composer install --no-dev' manually.${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}✓ Dependencies found${NC}"
fi

# Create build directory if it doesn't exist
if [ ! -d "$BUILD_DIR" ]; then
    mkdir -p "$BUILD_DIR"
    echo -e "${GREEN}✓ Created ${BUILD_DIR} directory${NC}"
fi

# Clean previous build
if [ -d "$TEMP_DIR" ]; then
    rm -rf "$TEMP_DIR"
fi
mkdir -p "$TEMP_DIR/$PLUGIN_SLUG"

echo -e "${YELLOW}📦 Copying plugin files...${NC}"

# Copy plugin files (excluding development files)
rsync -av \
    --exclude='node_modules' \
    --exclude='vendor/bin' \
    --exclude='vendor/composer/LICENSE' \
    --exclude='builder' \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.gitattributes' \
    --exclude='.github' \
    --exclude='build.sh' \
    --exclude='*.log' \
    --exclude='.DS_Store' \
    --exclude='DS_Store' \
    --exclude='*.md' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='yarn.lock' \
    --exclude='tests' \
    --exclude='test_*' \
    --exclude='*-test-*' \
    --exclude='*_test_*' \
    --exclude='.wordpress-org' \
    --exclude='*.zip' \
    --exclude='*.tar.gz' \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='phpunit.xml' \
    --exclude='.phpunit*' \
    --exclude='.vscode' \
    --exclude='.idea' \
    --exclude='*.swp' \
    --exclude='*.swo' \
    --exclude='*.swo*' \
    --exclude='#*#' \
    --exclude='debug-*' \
    --exclude='*.orig' \
    --exclude='*.bak' \
    --exclude='tmp/' \
    --exclude='temp/' \
    . "$TEMP_DIR/$PLUGIN_SLUG/"

echo -e "${GREEN}✓ Files copied${NC}"

# Create zip file
ZIP_FILE="$BUILD_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

echo -e "${YELLOW}📦 Creating zip file...${NC}"

# Remove old zip if exists
if [ -f "$ZIP_FILE" ]; then
    rm "$ZIP_FILE"
fi

# Create zip (use absolute path to avoid directory issues)
cd "$TEMP_DIR"
zip -r "../${PLUGIN_SLUG}-${VERSION}.zip" "$PLUGIN_SLUG" -x "*.git/*" -x "*.git*/*"
cd ../..

echo -e "${GREEN}✓ Zip file created: ${ZIP_FILE}${NC}"

# Clean up temp directory
rm -rf "$TEMP_DIR"
echo -e "${GREEN}✓ Cleaned up temporary files${NC}"

# Get file size
FILE_SIZE=$(du -h "$ZIP_FILE" | cut -f1)

# Count files in zip
FILE_COUNT=$(zipinfo -t "$ZIP_FILE" 2>/dev/null | grep "files$" | awk '{print $1}')

echo -e ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  ✅ Build Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "${BLUE}  File:     ${ZIP_FILE}${NC}"
echo -e "${BLUE}  Size:     ${FILE_SIZE}${NC}"
echo -e "${BLUE}  Files:    ${FILE_COUNT}${NC}"
echo -e "${BLUE}  Version:  ${VERSION}${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e ""
echo -e "${YELLOW}💡 Install this plugin by uploading the zip file to:${NC}"
echo -e "   WordPress Admin → Plugins → Add New → Upload Plugin"
echo -e ""