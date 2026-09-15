<?php

declare(strict_types=1);

final class Storage
{
    public static function ensureDirectories(string $root): void
    {
        $dirs = [
            $root . '/private',
            $root . '/private/uploads',
            $root . '/private/outputs',
            $root . '/private/logs',
            $root . '/private/jobs',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('Unable to create directory: ' . $dir);
            }
        }
    }

    public static function cleanup(string $root, int $ttlSeconds): void
    {
        $folders = [$root . '/private/uploads', $root . '/private/outputs'];
        $cutoff = time() - $ttlSeconds;

        foreach ($folders as $folder) {
            if (!is_dir($folder)) {
                continue;
            }

            $files = scandir($folder);
            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $path = $folder . '/' . $file;
                if (!is_file($path)) {
                    continue;
                }

                $modified = filemtime($path);
                if ($modified !== false && $modified < $cutoff) {
                    @unlink($path);
                }
            }
        }
    }

    public static function randomName(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }
}
