<?php

declare(strict_types=1);

return [
    'max_upload_bytes' => 500 * 1024 * 1024,
    'conversion_timeout_seconds' => 180,
    'cleanup_ttl_seconds' => 90 * 60,
    'allowed_extensions' => ['mp4', 'mov', 'avi', 'webm', 'mkv'],
    'default_fps' => 10,
    'default_width' => 480,
    'min_fps' => 5,
    'max_fps' => 20,
    'min_width' => 160,
    'max_width' => 900,
    'presets' => [
        'small' => ['colors' => 48, 'dither' => 'bayer', 'bayer_scale' => 5],
        'balanced' => ['colors' => 72, 'dither' => 'bayer', 'bayer_scale' => 3],
        'quality' => ['colors' => 96, 'dither' => 'sierra2_4a', 'bayer_scale' => 1],
    ],
    'default_preset' => 'small',
    'ffmpeg_path' => null,
    'ffprobe_path' => null,
];
