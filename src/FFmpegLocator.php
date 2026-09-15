<?php

declare(strict_types=1);

final class FFmpegLocator
{
    public static function locate(?string $configuredPath, string $binaryName): string
    {
        if (is_string($configuredPath) && $configuredPath !== '' && is_file($configuredPath)) {
            return $configuredPath;
        }

        $windowsCandidates = [
            'C:/xampp/ffmpeg/bin/' . $binaryName,
            'C:/ffmpeg/bin/' . $binaryName,
            'C:/Program Files/ffmpeg/bin/' . $binaryName,
            'C:/Program Files (x86)/ffmpeg/bin/' . $binaryName,
        ];

        foreach ($windowsCandidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $binaryStem = preg_replace('/[^a-zA-Z0-9_.-]/', '', pathinfo($binaryName, PATHINFO_FILENAME));
        $whereOutput = [];
        $exitCode = 1;
        @exec('where ' . $binaryStem . ' 2>nul', $whereOutput, $exitCode);
        if ($exitCode === 0 && !empty($whereOutput)) {
            $first = trim($whereOutput[0]);
            if ($first !== '' && is_file($first)) {
                return $first;
            }
        }

        throw new RuntimeException($binaryName . ' not found. Install FFmpeg and ensure ffmpeg/ffprobe are available.');
    }
}
