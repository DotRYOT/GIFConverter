<?php

declare(strict_types=1);

final class AppConfig
{
    public static function runtimeConfigPath(string $rootDir): string
    {
        return $rootDir . '/config/runtime.php';
    }

    public static function isConfigured(string $rootDir): bool
    {
        return is_file(self::runtimeConfigPath($rootDir));
    }

    public static function load(string $rootDir): array
    {
        $baseConfig = require $rootDir . '/config/app.php';
        $runtimePath = self::runtimeConfigPath($rootDir);

        if (!is_file($runtimePath)) {
            return $baseConfig;
        }

        $runtimeConfig = require $runtimePath;
        if (!is_array($runtimeConfig)) {
            return $baseConfig;
        }

        return array_replace_recursive($baseConfig, $runtimeConfig);
    }

    public static function writeRuntime(string $rootDir, array $config): void
    {
        $runtimePath = self::runtimeConfigPath($rootDir);
        $safeConfig = [
            'ffmpeg_path' => (string)($config['ffmpeg_path'] ?? ''),
            'ffprobe_path' => (string)($config['ffprobe_path'] ?? ''),
        ];

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($safeConfig, true) . ";\n";
        if (@file_put_contents($runtimePath, $content) === false) {
            throw new RuntimeException('Could not write runtime config file.');
        }
    }
}
