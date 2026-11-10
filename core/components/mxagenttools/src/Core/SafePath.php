<?php

namespace MxAgentTools\Core;

/**
 * Путь внутри разрешённого каталога — без MODX, чтобы проверяться тестами. Каталог и файл сводятся realpath:
 * «..», абсолютный путь и симлинк, ведущий наружу, дают отказ. Только чтение.
 */
final class SafePath
{
    /** Реальный корень; нет каталога — отказ */
    public static function root(string $dir): string
    {
        $real = $dir !== '' ? realpath($dir) : false;
        if ($real === false || !is_dir($real)) {
            throw new Refusal("каталог файловых элементов не найден: {$dir}");
        }
        return rtrim($real, '/') . '/';
    }

    /** Реальный путь $relative внутри $root (root — из root()); $dir — нужен каталог, иначе файл */
    public static function resolve(string $root, string $relative, bool $dir = false): string
    {
        if (strpos($relative, "\0") !== false) {
            throw new Refusal('недопустимый путь');
        }
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $real = realpath($root . $relative);
        if ($real === false) {
            throw new Refusal("нет такого " . ($dir ? 'каталога' : 'файла') . ": {$relative}");
        }
        $real .= is_dir($real) ? '/' : '';
        if (strpos($real, $root) !== 0) {
            throw new Refusal("{$relative}: вне каталога файловых элементов");
        }
        if ($dir !== is_dir($real)) {
            throw new Refusal("{$relative}: " . ($dir ? 'это файл, а не каталог' : 'это каталог, а не файл'));
        }
        return $real;
    }

    /** Путь от корня для ответа */
    public static function relative(string $root, string $real): string
    {
        return ltrim(substr($real, strlen($root)), '/');
    }
}
