#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting update process...${NC}"

# Check if git is installed
if ! command -v git &> /dev/null; then
    echo -e "${RED}Error: git is not installed. Please install git first.${NC}"
    exit 1
fi

# Check if we're in a git repository
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo -e "${RED}Error: Not a git repository. Please clone the repository first.${NC}"
    exit 1
fi

# Store current branch
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
echo -e "${YELLOW}Current branch: ${CURRENT_BRANCH}${NC}"

# Fetch latest changes from remote
echo -e "${YELLOW}Fetching latest changes from remote...${NC}"
git fetch origin

# Check if there are updates available
if [ "$CURRENT_BRANCH" = "main" ] || [ "$CURRENT_BRANCH" = "master" ]; then
    LOCAL=$(git rev-parse HEAD)
    REMOTE=$(git rev-parse @{u})
    
    if [ $LOCAL = $REMOTE ]; then
        echo -e "${GREEN}Already up to date!${NC}"
        exit 0
    fi
fi

# Pull latest changes
echo -e "${YELLOW}Pulling latest changes...${NC}"
if ! git pull origin $CURRENT_BRANCH; then
    echo -e "${RED}Error: Failed to pull changes. Please resolve conflicts manually.${NC}"
    exit 1
fi

echo -e "${GREEN}Successfully pulled latest changes!${NC}"

# Format code files (if prettier is available)
if command -v npx &> /dev/null; then
    echo -e "${YELLOW}Formatting code files...${NC}"
    
    # Format JavaScript, TypeScript, JSON, CSS, HTML files
    npx prettier --write "**/*.{js,ts,json,css,html,md}" 2>/dev/null || {
        echo -e "${YELLOW}Prettier not found or formatting failed. Skipping formatting step.${NC}"
        echo -e "${YELLOW}To install prettier, run: npm install -g prettier${NC}"
    }
else
    echo -e "${YELLOW}npx not found. Skipping formatting step.${NC}"
    echo -e "${YELLOW}To install Node.js and npm, visit: https://nodejs.org/${NC}"
fi

# Ensure required directories exist
echo -e "${YELLOW}Ensuring required directories exist...${NC}"

# Create download directory if it doesn't exist
DOWNLOAD_DIR="./download"
if [ ! -d "$DOWNLOAD_DIR" ]; then
    mkdir -p "$DOWNLOAD_DIR"
    chmod 755 "$DOWNLOAD_DIR"
    echo -e "${GREEN}Created download directory: $DOWNLOAD_DIR${NC}"
else
    echo -e "${GREEN}Download directory already exists: $DOWNLOAD_DIR${NC}"
fi

# Create temp directory if it doesn't exist
TEMP_DIR="./temp"
if [ ! -d "$TEMP_DIR" ]; then
    mkdir -p "$TEMP_DIR"
    chmod 755 "$TEMP_DIR"
    echo -e "${GREEN}Created temp directory: $TEMP_DIR${NC}"
else
    echo -e "${GREEN}Temp directory already exists: $TEMP_DIR${NC}"
fi

# Set proper permissions for web server
WEB_USER="www-data"
if id "$WEB_USER" &>/dev/null; then
    chown -R "$WEB_USER":"$WEB_USER" "$DOWNLOAD_DIR" "$TEMP_DIR" 2>/dev/null || true
    echo -e "${GREEN}Set ownership to $WEB_USER for download and temp directories${NC}"
fi

# Restart PHP-FPM if available (optional)
if command -v systemctl &> /dev/null; then
    if systemctl is-active --quiet php-fpm || systemctl is-active --quiet php8.1-fpm || systemctl is-active --quiet php8.2-fpm; then
        echo -e "${YELLOW}Restarting PHP-FPM service...${NC}"
        sudo systemctl restart php-fpm 2>/dev/null || \
        sudo systemctl restart php8.1-fpm 2>/dev/null || \
        sudo systemctl restart php8.2-fpm 2>/dev/null || \
        echo -e "${YELLOW}Could not restart PHP-FPM. You may need to restart it manually.${NC}"
    fi
fi

echo -e "${GREEN}Update completed successfully!${NC}"
echo -e "${YELLOW}Please clear your browser cache if you experience any issues.${NC}"
