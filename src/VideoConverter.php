<?php

declare(strict_types=1);

final class VideoConverter
{
    private string $ffmpeg;
    private string $ffprobe;
    private array $config;

    public function __construct(string $ffmpeg, string $ffprobe, array $config)
    {
        $this->ffmpeg = $ffmpeg;
        $this->ffprobe = $ffprobe;
        $this->config = $config;
    }

    public function probeDuration(string $inputPath): float
    {
        $info = $this->probeInfo($inputPath);
        return $info['duration'];
    }

    /** @return array{duration:float,width:int,height:int,fps:float} */
    public function probeInfo(string $inputPath): array
    {
        $cmd = sprintf(
            '%s -v error -select_streams v:0 -show_entries stream=width,height,r_frame_rate:format=duration -of json %s',
            escapeshellarg($this->ffprobe),
            escapeshellarg($inputPath)
        );

        [$exitCode, $stdout, $stderr] = $this->runCommandWithTimeout($cmd, 30);
        if ($exitCode !== 0) {
            throw new RuntimeException('ffprobe failed: ' . trim($stderr));
        }

        $json = json_decode($stdout, true);
        $duration = (float) ($json['format']['duration'] ?? 0);
        if ($duration <= 0) {
            throw new RuntimeException('Could not read video duration.');
        }

        $stream = $json['streams'][0] ?? [];
        $width = (int) ($stream['width'] ?? 0);
        $height = (int) ($stream['height'] ?? 0);
        $fps = 0.0;
        $fpsStr = (string) ($stream['r_frame_rate'] ?? '0/1');
        if (preg_match('/^(\d+)\/(\d+)$/', $fpsStr, $m) && (int) $m[2] > 0) {
            $fps = round((int) $m[1] / (int) $m[2], 2);
        }

        return ['duration' => $duration, 'width' => $width, 'height' => $height, 'fps' => $fps];
    }

    /**
     * @param callable|null $progressCallback  Receives (int $pct) periodically
     */
    public function convertToGif(
        string $inputPath,
        string $outputPath,
        int $fps,
        int $width,
        string $preset,
        float $startTime = 0.0,
        float $endTime = 0.0,
        string $crop = '',
        int $srcWidth = 0,
        int $srcHeight = 0,
        string $caption = '',
        string $captionPos = 'bottom',
        ?callable $progressCallback = null,
        float $totalDuration = 0.0
    ): void {
        $presetConfig = $this->config['presets'][$preset] ?? $this->config['presets'][$this->config['default_preset']];
        $colors = (int) $presetConfig['colors'];
        $dither = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $presetConfig['dither']) ?: 'bayer';
        $bayerScale = max(0, min(5, (int) $presetConfig['bayer_scale']));

        $filters = [];

        if ($crop !== '') {
            $cropFilter = $this->buildCropFilter($crop, $srcWidth, $srcHeight);
            if ($cropFilter !== '') {
                $filters[] = $cropFilter;
            }
        }

        $filters[] = sprintf('fps=%d', $fps);
        $filters[] = sprintf('scale=%d:-1:flags=lanczos', $width);

        if ($caption !== '') {
            $drawtextFilter = $this->buildDrawtextFilter($caption, $captionPos, $width);
            if ($drawtextFilter !== '') {
                $filters[] = $drawtextFilter;
            }
        }

        $filterChain = implode(',', $filters);
        $filter = sprintf(
            '%s,split[s0][s1];[s0]palettegen=max_colors=%d[p];[s1][p]paletteuse=dither=%s:bayer_scale=%d',
            $filterChain,
            $colors,
            $dither,
            $bayerScale
        );

        $ssArgs = '';
        $toArgs = '';
        if ($startTime > 0.0) {
            $ssArgs = sprintf('-ss %.3f ', $startTime);
        }
        if ($endTime > $startTime) {
            $duration = $endTime - $startTime;
            $toArgs = sprintf('-t %.3f ', $duration);
        }

        $cmd = sprintf(
            '%s -y %s%s-i %s -vf %s %s',
            escapeshellarg($this->ffmpeg),
            $ssArgs,
            $toArgs,
            escapeshellarg($inputPath),
            escapeshellarg($filter),
            escapeshellarg($outputPath)
        );

        $segmentDuration = ($endTime > $startTime) ? ($endTime - $startTime) : $totalDuration;

        [$exitCode, , $stderr] = $this->runCommandWithTimeout(
            $cmd,
            (int) $this->config['conversion_timeout_seconds'],
            $progressCallback,
            $segmentDuration
        );

        if ($exitCode !== 0 || !is_file($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException('Conversion failed: ' . trim($stderr));
        }
    }

    private function buildCropFilter(string $crop, int $srcWidth, int $srcHeight): string
    {
        if ($srcWidth <= 0 || $srcHeight <= 0) {
            return '';
        }

        switch ($crop) {
            case 'square':
                $size = min($srcWidth, $srcHeight);
                return sprintf('crop=%d:%d:(iw-%d)/2:(ih-%d)/2', $size, $size, $size, $size);
            case '16_9':
                $h = (int) round($srcWidth * 9 / 16);
                return sprintf('crop=%d:%d:0:(ih-%d)/2', $srcWidth, $h, $h);
            case '4_3':
                $h = (int) round($srcWidth * 3 / 4);
                return sprintf('crop=%d:%d:0:(ih-%d)/2', $srcWidth, $h, $h);
            case '9_16':
                $w = (int) round($srcHeight * 9 / 16);
                return sprintf('crop=%d:%d:(iw-%d)/2:0', $w, $srcHeight, $w);
            default:
                return '';
        }
    }

    private function buildDrawtextFilter(string $text, string $pos, int $width): string
    {
        // Only allow safe printable ASCII characters to prevent filter injection
        $clean = preg_replace('/[^a-zA-Z0-9 !@#\$%^&*()\-_+=\[\]{};",.<>?\/]/', '', $text);
        if ($clean === '' || $clean === null) {
            return '';
        }

        $fontSize = max(14, (int) round($width / 20));
        $yExpr = ($pos === 'top') ? '10' : '(h-th-10)';

        return sprintf(
            "drawtext=text='%s':fontsize=%d:fontcolor=white:x=(w-tw)/2:y=%s:box=1:boxcolor=black@0.55:boxborderw=6",
            addslashes($clean),
            $fontSize,
            $yExpr
        );
    }

    /**
     * @param callable|null $progressCallback  Receives (int $pct)
     */
    private function runCommandWithTimeout(
        string $command,
        int $timeoutSeconds,
        ?callable $progressCallback = null,
        float $totalDuration = 0.0
    ): array {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start process.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = time();
        $lastPct = 0;

        while (true) {
            $status = proc_get_status($process);
            $stdout .= stream_get_contents($pipes[1]);
            $chunk = stream_get_contents($pipes[2]);
            $stderr .= $chunk;

            if ($progressCallback !== null && $totalDuration > 0.0 && $chunk !== '') {
                if (preg_match_all('/time=(\d{2}):(\d{2}):(\d{2})\.(\d+)/', $chunk, $matches, PREG_SET_ORDER)) {
                    $lastMatch = end($matches);
                    $elapsed = (int) $lastMatch[1] * 3600
                        + (int) $lastMatch[2] * 60
                        + (int) $lastMatch[3]
                        + (float) ('0.' . $lastMatch[4]);
                    $pct = (int) min(99, round(($elapsed / $totalDuration) * 100));
                    if ($pct > $lastPct) {
                        $lastPct = $pct;
                        ($progressCallback)($pct);
                    }
                }
            }

            if ($status['running'] === false) {
                break;
            }

            if ((time() - $start) > $timeoutSeconds) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                throw new RuntimeException('Process timed out.');
            }

            usleep(100000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$exitCode, $stdout, $stderr];
    }
}
