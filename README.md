# Local Video to GIF Converter (FFmpeg)

Simple local web app (XAMPP + PHP) to convert video to GIF on your own hardware.

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
- Windows + XAMPP (Apache + PHP)
- FFmpeg installed (ffmpeg.exe and ffprobe.exe)
- PHP `proc_open` enabled

## Setup
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
