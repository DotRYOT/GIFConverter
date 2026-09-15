<?php

declare(strict_types=1);

require_once __DIR__ . '/src/AppConfig.php';
require_once __DIR__ . '/src/FFmpegLocator.php';
require_once __DIR__ . '/src/Storage.php';

if (AppConfig::isConfigured(__DIR__)) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = false;
$detectedFfmpeg = '';
$detectedFfprobe = '';
$procOpenEnabled = function_exists('proc_open');

try {
    $detectedFfmpeg = FFmpegLocator::locate(null, 'ffmpeg.exe');
} catch (Throwable $e) {
    $detectedFfmpeg = '';
}

try {
    $detectedFfprobe = FFmpegLocator::locate(null, 'ffprobe.exe');
} catch (Throwable $e) {
    $detectedFfprobe = '';
}

$ffmpegPath = (string)($_POST['ffmpeg_path'] ?? $detectedFfmpeg);
$ffprobePath = (string)($_POST['ffprobe_path'] ?? $detectedFfprobe);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$procOpenEnabled) {
            throw new RuntimeException('proc_open is disabled. Enable it in php.ini before continuing.');
        }

        if ($ffmpegPath === '' || !is_file($ffmpegPath)) {
            throw new RuntimeException('ffmpeg.exe path is missing or invalid.');
        }

        if ($ffprobePath === '' || !is_file($ffprobePath)) {
            throw new RuntimeException('ffprobe.exe path is missing or invalid.');
        }

        Storage::ensureDirectories(__DIR__);
        AppConfig::writeRuntime(__DIR__, [
            'ffmpeg_path' => $ffmpegPath,
            'ffprobe_path' => $ffprobePath,
        ]);

        $success = true;
        header('Refresh: 1; url=index.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>First-Time Setup</title>
    <link rel="icon" type="image/svg+xml" href="public/favicon.svg">
    <link rel="shortcut icon" href="public/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
<main class="wrap">
    <section class="card hero">
        <p class="eyebrow">First Launch</p>
        <h1>Quick Setup</h1>
        <p class="sub">We will auto-configure this app on your machine so users do not need to edit files manually.</p>
    </section>

    <section class="card">
        <?php if ($success): ?>
            <p class="status">Setup complete. Redirecting to converter...</p>
        <?php else: ?>
            <?php if ($error !== ''): ?>
                <p class="status" style="color:#c5462f;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <div class="setupGrid">
                <div>
                    <p class="status">proc_open available: <strong><?= $procOpenEnabled ? 'Yes' : 'No' ?></strong></p>
                    <p class="status">Detected ffmpeg.exe: <strong><?= $detectedFfmpeg !== '' ? htmlspecialchars($detectedFfmpeg, ENT_QUOTES, 'UTF-8') : 'Not found' ?></strong></p>
                    <p class="status">Detected ffprobe.exe: <strong><?= $detectedFfprobe !== '' ? htmlspecialchars($detectedFfprobe, ENT_QUOTES, 'UTF-8') : 'Not found' ?></strong></p>
                </div>
            </div>

            <form method="post" class="setupForm">
                <label class="field">
                    <span>ffmpeg.exe path</span>
                    <input type="text" name="ffmpeg_path" value="<?= htmlspecialchars($ffmpegPath, ENT_QUOTES, 'UTF-8') ?>" placeholder="E:/xampp/ffmpeg/bin/ffmpeg.exe" required>
                </label>

                <label class="field">
                    <span>ffprobe.exe path</span>
                    <input type="text" name="ffprobe_path" value="<?= htmlspecialchars($ffprobePath, ENT_QUOTES, 'UTF-8') ?>" placeholder="E:/xampp/ffmpeg/bin/ffprobe.exe" required>
                </label>

                <button type="submit">Complete Setup</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
