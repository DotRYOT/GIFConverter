# Local Video to GIF Converter (FFmpeg)

Simple local web app (PHP) to convert video to GIF on your own hardware.

## Features in v1
- Upload video
- Convert with FFmpeg
- Download GIF
- GIF preview after conversion
- Progress bar (upload + conversion phase)
- Dark mode toggle with system preference detection
- First-run setup page that auto-detects FFmpeg paths
- Defaults tuned for smaller file size

## Requirements
- Linux (Arch Linux) or Windows
- Web server (Apache/nginx) with PHP
- FFmpeg installed (`ffmpeg` and `ffprobe`)
- PHP `proc_open` enabled

## Setup on Arch Linux

### Quick Setup (Recommended)
1. Clone or copy this project to your web directory (e.g., `/srv/http/gifconverter` or `/var/www/html/gifconverter`)
2. Install required packages:
   ```bash
   sudo pacman -S apache php ffmpeg
   ```
3. Run the setup script:
   ```bash
   cd /path/to/GIFConverter
   sudo ./setup.sh
   ```
4. Start and enable Apache:
   ```bash
   sudo systemctl start httpd
   sudo systemctl enable httpd
   ```
5. Open `http://localhost/gifconverter/` in your browser.

### Manual Setup
1. Put project in your web root (e.g., `/srv/http/gifconverter`).
2. Install FFmpeg:
   ```bash
   sudo pacman -S ffmpeg
   ```
3. Ensure directories exist with proper permissions:
   ```bash
   mkdir -p private/uploads private/outputs private/logs private/jobs
   chown -R http:http private/
   chmod 700 private/uploads private/outputs private/logs private/jobs
   ```
4. Configure your web server to serve this directory.
5. Open `http://localhost/gifconverter/`.
6. On first launch, complete the guided setup page if `config/runtime.php` doesn't exist. It will auto-detect FFmpeg paths.

## Setup on Windows
1. Put project in `e:/xampp/htdocs/GIFConverter`.
2. Install FFmpeg and ensure binaries are discoverable:
   - `C:/xampp/ffmpeg/bin/ffmpeg.exe` and `C:/xampp/ffmpeg/bin/ffprobe.exe`, or
   - `E:/xampp/ffmpeg/bin/ffmpeg.exe` and `E:/xampp/ffmpeg/bin/ffprobe.exe`, or
   - available on system `PATH`.
3. Start Apache from XAMPP.
4. Open `http://localhost/GIFConverter/`.
5. On first launch, complete the guided setup page. It auto-detects FFmpeg and writes `config/runtime.php`.

## API
- `GET /GIFConverter/api.php?action=health`
- `POST /GIFConverter/api.php` with `action=convert` and file field `video`
- `GET /GIFConverter/api.php?action=download&file=<token>.gif`
- `GET /GIFConverter/api.php?action=preview&file=<token>.gif`

## Notes
- Max upload size default: 500 MB.
- Temporary files are auto-cleaned by TTL.
