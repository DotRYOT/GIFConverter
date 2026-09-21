#!/bin/bash

# Setup script for Video to GIF Converter on Arch Linux
# This script creates necessary directories and sets proper permissions

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$SCRIPT_DIR"

# Detect web server user (common on Arch Linux)
# Apache uses 'http', nginx typically uses 'http' as well
WEB_USER="http"
WEB_GROUP="http"

# Check if running as root
if [[ $EUID -ne 0 ]]; then
    echo "This script must be run as root (use sudo)"
    exit 1
fi

echo "=== Video to GIF Converter Setup for Arch Linux ==="
echo ""

# Check if web server user exists, if not try alternatives
if ! id "$WEB_USER" &>/dev/null; then
    echo "Warning: User '$WEB_USER' not found."
    echo "Please ensure Apache or nginx is installed."
    echo "Install with: sudo pacman -S apache or sudo pacman -S nginx"
    exit 1
fi

echo "Using web server user: $WEB_USER:$WEB_GROUP"
echo ""

# Create necessary directories
echo "Creating required directories..."

DIRS=(
    "$PROJECT_DIR/private"
    "$PROJECT_DIR/private/uploads"
    "$PROJECT_DIR/private/outputs"
    "$PROJECT_DIR/private/logs"
    "$PROJECT_DIR/private/jobs"
    "$PROJECT_DIR/config"
)

for dir in "${DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        echo "  Created: $dir"
    else
        echo "  Exists: $dir"
    fi
done

echo ""
echo "Setting directory permissions..."

# Set ownership to web server user for private directories
chown -R "$WEB_USER:$WEB_GROUP" "$PROJECT_DIR/private"
chown "$WEB_USER:$WEB_GROUP" "$PROJECT_DIR/config"

# Set permissions
# Directories: 755 (rwxr-xr-x) - owner can read/write/execute, others can read/execute
# Files will inherit proper permissions through umask or explicit setting
chmod 755 "$PROJECT_DIR/private"
chmod 700 "$PROJECT_DIR/private/uploads"
chmod 700 "$PROJECT_DIR/private/outputs"
chmod 700 "$PROJECT_DIR/private/logs"
chmod 700 "$PROJECT_DIR/private/jobs"
chmod 755 "$PROJECT_DIR/config"

# Make config files readable by web server but not world-writable
if [ -f "$PROJECT_DIR/config/app.php" ]; then
    chmod 644 "$PROJECT_DIR/config/app.php"
    chown "root:$WEB_GROUP" "$PROJECT_DIR/config/app.php"
fi

# Set permissions for PHP files
find "$PROJECT_DIR" -name "*.php" -type f -exec chmod 644 {} \;

# Set permissions for public assets
if [ -d "$PROJECT_DIR/public" ]; then
    find "$PROJECT_DIR/public" -type d -exec chmod 755 {} \;
    find "$PROJECT_DIR/public" -type f -exec chmod 644 {} \;
fi

echo ""
echo "Directory permissions set successfully!"
echo ""

# Check for FFmpeg installation
echo "Checking for FFmpeg installation..."
if command -v ffmpeg &> /dev/null && command -v ffprobe &> /dev/null; then
    FFMPEG_PATH=$(which ffmpeg)
    FFPROBE_PATH=$(which ffprobe)
    echo "  ffmpeg found: $FFMPEG_PATH"
    echo "  ffprobe found: $FFPROBE_PATH"
    
    # Create runtime config automatically
    RUNTIME_CONFIG="$PROJECT_DIR/config/runtime.php"
    cat > "$RUNTIME_CONFIG" << EOF
<?php

declare(strict_types=1);

return [
    'ffmpeg_path' => '$FFMPEG_PATH',
    'ffprobe_path' => '$FFPROBE_PATH',
];
EOF
    
    chmod 644 "$RUNTIME_CONFIG"
    chown "root:$WEB_GROUP" "$RUNTIME_CONFIG"
    
    echo ""
    echo "Runtime configuration created at: $RUNTIME_CONFIG"
else
    echo "  Warning: FFmpeg not found!"
    echo "  Install with: sudo pacman -S ffmpeg"
    echo "  You will need to complete setup via the web interface."
fi

echo ""
echo "=== Setup Complete ==="
echo ""
echo "Next steps:"
echo "1. Ensure your web server (Apache/nginx) is running:"
echo "   sudo systemctl start httpd    # for Apache"
echo "   sudo systemctl enable httpd   # for Apache"
echo ""
echo "2. Configure your web server to serve this directory"
echo "   Example Apache vhost or place in /srv/http/"
echo ""
echo "3. Access the application via your browser"
echo ""
echo "For manual configuration, edit: $PROJECT_DIR/config/runtime.php"
