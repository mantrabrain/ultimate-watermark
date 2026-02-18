#!/bin/bash

# Ultimate Watermark - Production Build Script
# Generates production-ready zip file in dist folder

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Plugin information
PLUGIN_SLUG="ultimate-watermark"
PLUGIN_NAME="Ultimate Watermark"
BUILD_DIR="dist"
TEMP_DIR="$BUILD_DIR/temp"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Building ${PLUGIN_NAME}${NC}"
echo -e "${GREEN}========================================${NC}"

# Get plugin version from main file
VERSION=$(grep "Version:" ultimate-watermark.php | awk '{print $2}')
echo -e "${YELLOW}Version: ${VERSION}${NC}"

# Create dist directory if it doesn't exist
if [ ! -d "$BUILD_DIR" ]; then
    mkdir -p "$BUILD_DIR"
    echo -e "${GREEN}✓ Created dist directory${NC}"
fi

# Clean previous build
if [ -d "$TEMP_DIR" ]; then
    rm -rf "$TEMP_DIR"
fi
mkdir -p "$TEMP_DIR/$PLUGIN_SLUG"

echo -e "${YELLOW}Copying plugin files...${NC}"

# Copy plugin files (excluding development files)
rsync -av --progress \
    --exclude='node_modules' \
    --exclude='dist' \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.gitattributes' \
    --exclude='.github' \
    --exclude='build.sh' \
    --exclude='*.log' \
    --exclude='.DS_Store' \
    --exclude='*.md' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='tests' \
    --exclude='.wordpress-org' \
    --exclude='*.zip' \
    . "$TEMP_DIR/$PLUGIN_SLUG/"

echo -e "${GREEN}✓ Files copied${NC}"

# Create zip file
ZIP_FILE="$BUILD_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

echo -e "${YELLOW}Creating zip file...${NC}"

# Remove old zip if exists
if [ -f "$ZIP_FILE" ]; then
    rm "$ZIP_FILE"
fi

# Create zip
cd "$TEMP_DIR"
zip -r "../../${PLUGIN_SLUG}-${VERSION}.zip" "$PLUGIN_SLUG" -q
cd ../..

echo -e "${GREEN}✓ Zip file created: ${ZIP_FILE}${NC}"

# Clean up temp directory
rm -rf "$TEMP_DIR"
echo -e "${GREEN}✓ Cleaned up temporary files${NC}"

# Get file size
FILE_SIZE=$(du -h "$ZIP_FILE" | cut -f1)

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Build Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "${YELLOW}File: ${ZIP_FILE}${NC}"
echo -e "${YELLOW}Size: ${FILE_SIZE}${NC}"
echo -e "${GREEN}========================================${NC}"
