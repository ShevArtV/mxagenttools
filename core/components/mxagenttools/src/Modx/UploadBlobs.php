<?php

namespace MxAgentTools\Modx;

use RuntimeException;

/** Содержимое загрузок — файлами в каталоге внутри сайта (имя = токен), а не в БД: base64 в longtext упирается в max_allowed_packet */
class UploadBlobs
{
    /** @var string */
    private $dir;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/');
    }

    public function put(string $token, string $data): void
    {
        $path = $this->path($token);
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            throw new RuntimeException('каталог загрузок недоступен для записи');
        }
        if (@file_put_contents($path, $data, LOCK_EX) !== strlen($data)) {
            @unlink($path);
            throw new RuntimeException('файл загрузки не записался');
        }
    }

    public function get(string $token): ?string
    {
        $path = $this->path($token);
        $data = is_file($path) ? @file_get_contents($path) : false;
        return $data === false ? null : $data;
    }

    public function drop(string $token): void
    {
        @unlink($this->path($token));
    }

    private function path(string $token): string
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
            throw new RuntimeException('неверный токен загрузки');
        }
        return $this->dir . '/' . $token . '.bin';
    }
}
