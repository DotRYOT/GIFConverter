<?php

declare(strict_types=1);

final class Logger
{
    public static function log(string $root, string $message): void
    {
        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
        @file_put_contents($root . '/private/logs/conversions.log', $line, FILE_APPEND);
    }
}
