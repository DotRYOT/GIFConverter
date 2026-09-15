<?php
declare(strict_types=1);

require_once __DIR__ . '/src/ApiResponse.php';
require_once __DIR__ . '/src/AppConfig.php';
require_once __DIR__ . '/src/FFmpegLocator.php';
require_once __DIR__ . '/src/VideoConverter.php';
require_once __DIR__ . '/src/Storage.php';
require_once __DIR__ . '/src/Logger.php';
require_once __DIR__ . '/src/JobManager.php';

function buildDownloadName(string $sourceName): string
{
  $base = pathinfo($sourceName, PATHINFO_FILENAME);
  $base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $base) ?? 'converted';
  $base = preg_replace('/-{2,}/', '-', $base);
  $base = trim($base, '-._');
  if ($base === '') {
    $base = 'converted';
  }

  return $base . '.gif';
}

$config = AppConfig::load(__DIR__);

try {
  if (!AppConfig::isConfigured(__DIR__)) {
    ApiResponse::fail(503, 'setup_required', 'Run setup.php before using the converter.');
    exit;
  }

  Storage::ensureDirectories(__DIR__);
  Storage::cleanup(__DIR__, (int) $config['cleanup_ttl_seconds']);

  $jobs = new JobManager(__DIR__);
  $jobs->cleanup((int) $config['cleanup_ttl_seconds']);

  $action = strtolower((string) ($_GET['action'] ?? $_POST['action'] ?? 'health'));

  // ── health ──────────────────────────────────────────────────────────────
  if ($action === 'health') {
    $ffmpeg = FFmpegLocator::locate($config['ffmpeg_path'], 'ffmpeg.exe');
    $ffprobe = FFmpegLocator::locate($config['ffprobe_path'], 'ffprobe.exe');
    ApiResponse::ok([
      'ffmpeg' => $ffmpeg,
      'ffprobe' => $ffprobe,
      'maxUploadMB' => (int) floor($config['max_upload_bytes'] / 1024 / 1024),
      'defaultPreset' => $config['default_preset'],
    ]);
    exit;
  }

  // ── probe ────────────────────────────────────────────────────────────────
  if ($action === 'probe') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      ApiResponse::fail(405, 'method_not_allowed', 'POST required.');
      exit;
    }

    if (!isset($_FILES['video'])) {
      ApiResponse::fail(400, 'missing_file', 'No video file uploaded.');
      exit;
    }

    $upload = $_FILES['video'];
    if (!is_array($upload) || (int) $upload['error'] !== UPLOAD_ERR_OK) {
      ApiResponse::fail(400, 'upload_failed', 'Upload failed.');
      exit;
    }

    $size = (int) $upload['size'];
    if ($size <= 0 || $size > (int) $config['max_upload_bytes']) {
      ApiResponse::fail(400, 'invalid_size', 'File is too large or empty.');
      exit;
    }

    $originalName = (string) $upload['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $config['allowed_extensions'], true)) {
      ApiResponse::fail(400, 'invalid_type', 'Unsupported file type.');
      exit;
    }

    $tmpPath = (string) $upload['tmp_name'];
    if (!is_uploaded_file($tmpPath)) {
      ApiResponse::fail(400, 'invalid_upload', 'Upload payload was not recognized as a file upload.');
      exit;
    }

    $jobId = $jobs->create();
    $inputPath = $jobs->inputPath($jobId, $ext);
    if (!move_uploaded_file($tmpPath, $inputPath)) {
      ApiResponse::fail(500, 'store_failed', 'Could not store uploaded file.');
      exit;
    }

    $ffprobe = FFmpegLocator::locate($config['ffprobe_path'], 'ffprobe.exe');
    $ffmpeg  = FFmpegLocator::locate($config['ffmpeg_path'], 'ffmpeg.exe');
    $converter = new VideoConverter($ffmpeg, $ffprobe, $config);

    $info = $converter->probeInfo($inputPath);

    $jobs->writeMeta($jobId, array_merge($info, ['ext' => $ext, 'fileName' => $originalName]));
    $jobs->writeProgress($jobId, 0);

    ApiResponse::ok([
      'jobId'    => $jobId,
      'duration' => $info['duration'],
      'width'    => $info['width'],
      'height'   => $info['height'],
      'fps'      => $info['fps'],
      'fileName' => $originalName,
    ]);
    exit;
  }

  // ── progress ─────────────────────────────────────────────────────────────
  if ($action === 'progress') {
    $jobId = (string) ($_GET['job'] ?? $_POST['job'] ?? '');
    try {
      $jobs->validate($jobId);
    } catch (RuntimeException $e) {
      ApiResponse::fail(400, 'invalid_job', 'Invalid job ID.');
      exit;
    }

    if (!$jobs->exists($jobId)) {
      ApiResponse::fail(404, 'not_found', 'Job not found.');
      exit;
    }

    ApiResponse::ok(['pct' => $jobs->readProgress($jobId)]);
    exit;
  }

  // ── download / preview ────────────────────────────────────────────────────
  if ($action === 'download' || $action === 'preview') {
    $jobId = (string) ($_GET['job'] ?? '');
    $dlName = 'converted.gif';
    if ($jobId !== '') {
      try {
        $jobs->validate($jobId);
      } catch (RuntimeException $e) {
        ApiResponse::fail(400, 'invalid_job', 'Invalid job ID.');
        exit;
      }
      $path = $jobs->outputPath($jobId);
      $meta = $jobs->readMeta($jobId);
      $dlName = buildDownloadName((string) ($meta['fileName'] ?? 'converted'));
    } else {
      $file = (string) ($_GET['file'] ?? '');
      if (!preg_match('/^[a-f0-9]{32}\.gif$/', $file)) {
        ApiResponse::fail(400, 'invalid_file', 'Invalid download token.');
        exit;
      }
      $path = __DIR__ . '/private/outputs/' . $file;
    }

    if (!is_file($path)) {
      ApiResponse::fail(404, 'not_found', 'File not found or expired.');
      exit;
    }

    // RFC 5987 encoding so all browsers (including mobile Safari) use the correct filename
    $encodedName = rawurlencode($dlName);
    header('Content-Type: image/gif');
    header('Content-Length: ' . filesize($path));
    if ($action === 'download') {
      header("Content-Disposition: attachment; filename=\"{$dlName}\"; filename*=UTF-8''{$encodedName}");
    } else {
      header("Content-Disposition: inline; filename=\"{$dlName}\"; filename*=UTF-8''{$encodedName}");
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
    readfile($path);
    exit;
  }

  // ── convert ───────────────────────────────────────────────────────────────
  if ($action !== 'convert') {
    ApiResponse::fail(404, 'unknown_action', 'Unsupported action.');
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::fail(405, 'method_not_allowed', 'POST required.');
    exit;
  }

  $jobId = (string) ($_POST['job_id'] ?? '');
  if ($jobId !== '') {
    // Job-based convert
    try {
      $jobs->validate($jobId);
    } catch (RuntimeException $e) {
      ApiResponse::fail(400, 'invalid_job', 'Invalid job ID.');
      exit;
    }

    if (!$jobs->exists($jobId)) {
      ApiResponse::fail(404, 'not_found', 'Job not found.');
      exit;
    }

    $meta      = $jobs->readMeta($jobId);
    $ext       = $meta['ext'] ?? 'mp4';
    $inputPath = $jobs->inputPath($jobId, $ext);

    if (!is_file($inputPath)) {
      ApiResponse::fail(400, 'missing_input', 'Input file not found for job.');
      exit;
    }

    $fps        = (int) ($_POST['fps'] ?? $config['default_fps']);
    $width      = (int) ($_POST['width'] ?? $config['default_width']);
    $preset     = strtolower((string) ($_POST['preset'] ?? $config['default_preset']));
    $startTime  = (float) ($_POST['start_time'] ?? 0.0);
    $endTime    = (float) ($_POST['end_time'] ?? 0.0);
    $crop       = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_POST['crop'] ?? '')));
    $caption    = substr((string) ($_POST['caption'] ?? ''), 0, 120);
    $captionPos = in_array((string) ($_POST['caption_pos'] ?? 'bottom'), ['top', 'bottom'], true)
      ? (string) $_POST['caption_pos']
      : 'bottom';

    $fps   = max((int) $config['min_fps'], min((int) $config['max_fps'], $fps));
    $width = max((int) $config['min_width'], min((int) $config['max_width'], $width));
    if (!isset($config['presets'][$preset])) {
      $preset = $config['default_preset'];
    }

    $totalDuration = (float) ($meta['duration'] ?? 0.0);
    $srcWidth      = (int) ($meta['width'] ?? 0);
    $srcHeight     = (int) ($meta['height'] ?? 0);
    $outputPath    = $jobs->outputPath($jobId);

    $jobs->writeProgress($jobId, 0);

    $ffmpeg    = FFmpegLocator::locate($config['ffmpeg_path'], 'ffmpeg.exe');
    $ffprobe   = FFmpegLocator::locate($config['ffprobe_path'], 'ffprobe.exe');
    $converter = new VideoConverter($ffmpeg, $ffprobe, $config);

    $progressCallback = static function (int $pct) use ($jobs, $jobId): void {
      $jobs->writeProgress($jobId, $pct);
    };

    $start = microtime(true);
    $converter->convertToGif(
      $inputPath, $outputPath, $fps, $width, $preset,
      $startTime, $endTime, $crop, $srcWidth, $srcHeight,
      $caption, $captionPos, $progressCallback, $totalDuration
    );
    $elapsedMs = (int) round((microtime(true) - $start) * 1000);

    $jobs->writeProgress($jobId, 100);
    $gifSize = filesize($outputPath);

    Logger::log(__DIR__, sprintf(
      'converted job=%s fps=%d width=%d preset=%s outBytes=%d elapsedMs=%d',
      $jobId, $fps, $width, $preset, (int) $gifSize, $elapsedMs
    ));

    ApiResponse::ok([
      'downloadUrl'   => 'download/' . rawurlencode($jobId) . '/' . rawurlencode(buildDownloadName((string) ($meta['fileName'] ?? 'converted'))),
      'downloadName'  => buildDownloadName((string) ($meta['fileName'] ?? 'converted')),
      'previewUrl'    => 'api.php?action=preview&job=' . urlencode($jobId),
      'gifBytes'      => (int) $gifSize,
      'inputDuration' => $totalDuration,
      'elapsedMs'     => $elapsedMs,
      'settings'      => ['fps' => $fps, 'width' => $width, 'preset' => $preset],
    ]);
    exit;
  }

  // Legacy single-upload convert (no job_id provided)
  if (!isset($_FILES['video'])) {
    ApiResponse::fail(400, 'missing_file', 'No video file uploaded.');
    exit;
  }

  $upload = $_FILES['video'];
  if (!is_array($upload) || (int) $upload['error'] !== UPLOAD_ERR_OK) {
    ApiResponse::fail(400, 'upload_failed', 'Upload failed.');
    exit;
  }

  $size = (int) $upload['size'];
  if ($size <= 0 || $size > (int) $config['max_upload_bytes']) {
    ApiResponse::fail(400, 'invalid_size', 'File is too large or empty.');
    exit;
  }

  $originalName = (string) $upload['name'];
  $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
  if (!in_array($ext, $config['allowed_extensions'], true)) {
    ApiResponse::fail(400, 'invalid_type', 'Unsupported file type.');
    exit;
  }

  $fps    = (int) ($_POST['fps'] ?? $config['default_fps']);
  $width  = (int) ($_POST['width'] ?? $config['default_width']);
  $preset = strtolower((string) ($_POST['preset'] ?? $config['default_preset']));

  $fps   = max((int) $config['min_fps'], min((int) $config['max_fps'], $fps));
  $width = max((int) $config['min_width'], min((int) $config['max_width'], $width));
  if (!isset($config['presets'][$preset])) {
    $preset = $config['default_preset'];
  }

  $ffmpeg    = FFmpegLocator::locate($config['ffmpeg_path'], 'ffmpeg.exe');
  $ffprobe   = FFmpegLocator::locate($config['ffprobe_path'], 'ffprobe.exe');
  $converter = new VideoConverter($ffmpeg, $ffprobe, $config);

  $inputFile  = Storage::randomName($ext);
  $outputFile = Storage::randomName('gif');
  $inputPath  = __DIR__ . '/private/uploads/' . $inputFile;
  $outputPath = __DIR__ . '/private/outputs/' . $outputFile;

  $tmpPath = (string) $upload['tmp_name'];
  if (!is_uploaded_file($tmpPath)) {
    ApiResponse::fail(400, 'invalid_upload', 'Upload payload was not recognized as a file upload.');
    exit;
  }

  if (!move_uploaded_file($tmpPath, $inputPath)) {
    ApiResponse::fail(500, 'store_failed', 'Could not store uploaded file.');
    exit;
  }

  $duration = $converter->probeDuration($inputPath);

  $start     = microtime(true);
  $converter->convertToGif($inputPath, $outputPath, $fps, $width, $preset);
  $elapsedMs = (int) round((microtime(true) - $start) * 1000);

  $gifSize = filesize($outputPath);
  Logger::log(__DIR__, sprintf(
    'converted input=%s output=%s duration=%.2fs fps=%d width=%d preset=%s outBytes=%d elapsedMs=%d',
    $inputFile, $outputFile, $duration, $fps, $width, $preset, (int) $gifSize, $elapsedMs
  ));

  ApiResponse::ok([
    'downloadUrl'   => 'api.php?action=download&file=' . urlencode($outputFile),
    'downloadName'  => buildDownloadName($originalName),
    'previewUrl'    => 'api.php?action=preview&file=' . urlencode($outputFile),
    'gifBytes'      => (int) $gifSize,
    'inputDuration' => $duration,
    'elapsedMs'     => $elapsedMs,
    'settings'      => ['fps' => $fps, 'width' => $width, 'preset' => $preset],
  ]);
} catch (Throwable $e) {
  Logger::log(__DIR__, 'error: ' . $e->getMessage());
  ApiResponse::fail(500, 'internal_error', $e->getMessage());
}
