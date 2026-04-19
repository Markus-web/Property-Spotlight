#!/bin/bash
# Property Spotlight - Build Production ZIP
# Usage: ./build-zip.sh

set -e

# Configuration
PLUGIN_NAME="property-spotlight"
VERSION="1.0.0"
OUTPUT_FILE="${PLUGIN_NAME}.zip"

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo -e "\033[36mBuilding $PLUGIN_NAME v$VERSION...\033[0m"

# Files and folders to include
INCLUDE_ITEMS=(
    "assets"
    "blocks"
    "includes"
    "languages"
    "index.php"
    "property-spotlight.php"
    "uninstall.php"
    "LICENSE"
    "readme.txt"
)

# Files to exclude (within included folders)
EXCLUDE_PATTERNS=(
    "*.zip"
    "docker-compose.yml"
    "build-zip.ps1"
    "build-zip.sh"
    "README.md"
    "compile-mo.php"
)

# Clean up old ZIP
if [ -f "$OUTPUT_FILE" ]; then
    rm -f "$OUTPUT_FILE"
    echo -e "  \033[33mRemoved old $OUTPUT_FILE\033[0m"
fi

# Create temp directory
TEMP_DIR=$(mktemp -d)
TEMP_PLUGIN_DIR="$TEMP_DIR/$PLUGIN_NAME"
mkdir -p "$TEMP_PLUGIN_DIR"

# Copy files
echo -e "  \033[90mCopying files...\033[0m"
for item in "${INCLUDE_ITEMS[@]}"; do
    if [ -e "$SCRIPT_DIR/$item" ]; then
        cp -r "$SCRIPT_DIR/$item" "$TEMP_PLUGIN_DIR/"
        echo -e "    \033[32m+ $item\033[0m"
    else
        echo -e "    \033[33m! $item not found\033[0m"
    fi
done

# Remove excluded files from temp directory
for pattern in "${EXCLUDE_PATTERNS[@]}"; do
    find "$TEMP_PLUGIN_DIR" -name "$pattern" -type f -delete 2>/dev/null || true
done

# Create ZIP
echo -e "  \033[90mCreating ZIP archive...\033[0m"
cd "$TEMP_DIR"
zip -rq "$SCRIPT_DIR/$OUTPUT_FILE" "$PLUGIN_NAME"
cd "$SCRIPT_DIR"

# Clean up temp directory
rm -rf "$TEMP_DIR"

# Verify ZIP structure
echo -e "  \033[90mVerifying ZIP structure...\033[0m"
ZIP_VALID=true
MAIN_FILE_FOUND=false
ENTRY_COUNT=0

while IFS= read -r entry; do
    ((ENTRY_COUNT++)) || true
    
    # Check that all entries start with plugin folder name
    if [[ ! "$entry" =~ ^${PLUGIN_NAME}/ ]] && [[ "$entry" != "${PLUGIN_NAME}/" ]]; then
        echo -e "    \033[31m! Invalid path: $entry\033[0m"
        ZIP_VALID=false
    fi
    
    # Check for main plugin file
    if [[ "$entry" == "${PLUGIN_NAME}/${PLUGIN_NAME}.php" ]]; then
        MAIN_FILE_FOUND=true
    fi
    
    # Check for double-nesting
    if [[ "$entry" =~ ^${PLUGIN_NAME}/${PLUGIN_NAME}/ ]]; then
        echo -e "    \033[31m! Double-nested: $entry\033[0m"
        ZIP_VALID=false
    fi
done < <(unzip -l "$OUTPUT_FILE" | awk 'NR>3 {print $4}' | grep -v '^$' | head -n -2)

if [ "$MAIN_FILE_FOUND" = false ]; then
    echo -e "\033[31mERROR: Main plugin file ($PLUGIN_NAME/$PLUGIN_NAME.php) not found in ZIP\033[0m"
    exit 1
fi

if [ "$ZIP_VALID" = false ]; then
    echo -e "\033[31mERROR: ZIP structure is invalid\033[0m"
    exit 1
fi

# Success output
if [ -f "$OUTPUT_FILE" ]; then
    ZIP_SIZE=$(du -k "$OUTPUT_FILE" | cut -f1)
    echo ""
    echo -e "\033[32mSUCCESS: Created $OUTPUT_FILE (${ZIP_SIZE} KB, $ENTRY_COUNT files)\033[0m"
    echo -e "\033[32m  Structure: $PLUGIN_NAME/$PLUGIN_NAME.php verified\033[0m"
    echo ""
    echo -e "\033[36mInstall via: WordPress Admin > Plugins > Add New > Upload Plugin\033[0m"
else
    echo -e "\033[31mERROR: Failed to create ZIP file\033[0m"
    exit 1
fi
