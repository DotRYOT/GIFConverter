<?php

declare(strict_types=1);

final class JobManager
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = $root;
    }

    public function create(): string
    {
        $jobId = bin2hex(random_bytes(16));
        $dir = $this->dir($jobId);
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create job directory.');
        }
        return $jobId;
    }

    public function dir(string $jobId): string
    {
        $this->validate($jobId);
        return $this->root . '/private/jobs/' . $jobId;
    }

    public function inputPath(string $jobId, string $ext): string
    {
        $safeExt = preg_replace('/[^a-z0-9]/', '', strtolower($ext));
        return $this->dir($jobId) . '/input.' . $safeExt;
    }

    public function outputPath(string $jobId): string
    {
        return $this->dir($jobId) . '/output.gif';
    }

    public function writeMeta(string $jobId, array $data): void
    {
        file_put_contents(
            $this->dir($jobId) . '/meta.json',
            json_encode($data, JSON_THROW_ON_ERROR)
        );
    }

    public function readMeta(string $jobId): array
    {
        $path = $this->dir($jobId) . '/meta.json';
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        return (array) json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    public function writeProgress(string $jobId, int $pct): void
    {
        file_put_contents(
            $this->dir($jobId) . '/progress.json',
            json_encode(['pct' => max(0, min(100, $pct))], JSON_THROW_ON_ERROR)
        );
    }

    public function readProgress(string $jobId): int
    {
        $path = $this->dir($jobId) . '/progress.json';
        if (!is_file($path)) {
            return 0;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return 0;
        }
        $data = json_decode($raw, true);
        return (int) (is_array($data) ? ($data['pct'] ?? 0) : 0);
    }

    public function exists(string $jobId): bool
    {
        try {
            return is_dir($this->dir($jobId));
        } catch (RuntimeException $e) {
            return false;
        }
    }

    public function validate(string $jobId): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
            throw new RuntimeException('Invalid job ID.');
        }
    }

    public function cleanup(int $ttlSeconds): void
    {
        $base = $this->root . '/private/jobs';
        if (!is_dir($base)) {
            return;
        }
        $cutoff = time() - $ttlSeconds;
        $entries = scandir($base);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $dir = $base . '/' . $entry;
            if (!is_dir($dir)) {
                continue;
            }
            $mtime = filemtime($dir);
            if ($mtime !== false && $mtime < $cutoff) {
                $this->removeDir($dir);
            }
        }
    }

    private function removeDir(string $dir): void
    {
        $files = scandir($dir);
        if ($files !== false) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $path = $dir . '/' . $file;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
        @rmdir($dir);
    }
}
