<?php

namespace MxAgentTools\Core;

use MxAgentTools\Image\ImageOptions;
use MxAgentTools\Image\Images;
use RuntimeException;

/**
 * Файлы от агента (POST /agent/uploads): base64 в теле → проверка размера и sha256 → хранилище по token.
 * Инструмент записи получает файл по token: path() выкладывает байты во временный файл <tmp>/<token>/<имя>
 * (ms2 gallery/upload берёт файл по пути и название фото — из имени файла, поэтому имя сохраняется).
 * Просроченное чистится при каждом приёме. Картинку с параметрами сжатия (ImageOptions) Image\Images ужимает до
 * сохранения: sha256 сверяется по присланному, хранится и отдаётся — ужатое.
 */
class Uploads
{
    const TOKEN_RE = '/^[0-9a-f]{32}$/';

    /** @var UploadStorageInterface */
    private $storage;
    /** @var int */
    private $ttl;
    /** @var int */
    private $maxBytes;
    /** @var string */
    private $tmp;
    /** @var Images|null */
    private $images;

    public function __construct(UploadStorageInterface $storage, int $ttlSeconds = 3600, int $maxBytes = 20971520, string $tmp = '',
        ?Images $images = null)
    {
        $this->images = $images;
        $this->storage = $storage;
        $this->ttl = $ttlSeconds;
        $this->maxBytes = $maxBytes;
        $this->tmp = rtrim($tmp !== '' ? $tmp : sys_get_temp_dir(), '/') . '/mxagenttools';
    }

    /** Принять файл: $base64 — содержимое, $sha256 — необязательная сверка */
    public function put(string $base64, string $name, string $sha256, ?ImageOptions $image = null): array
    {
        $this->storage->purge(time());
        $name = self::cleanName($name);
        if ($name === '') {
            throw new Refusal('у файла нет имени');
        }
        if (strlen($base64) > intdiv($this->maxBytes * 4, 3) + 8) {
            throw new Refusal('файл больше ' . round($this->maxBytes / 1048576) . ' МиБ — пришлите уменьшенный');
        }
        $data = base64_decode(preg_replace('/\s+/', '', $base64), true);
        if ($data === false || $data === '') {
            throw new Refusal('data: нужно непустое содержимое файла в base64');
        }
        if (strlen($data) > $this->maxBytes) {
            throw new Refusal('файл больше ' . round($this->maxBytes / 1048576) . ' МиБ — пришлите уменьшенный');
        }
        $hash = hash('sha256', $data);
        if ($sha256 !== '' && !hash_equals(strtolower($sha256), $hash)) {
            throw new Refusal('файл пришёл повреждённым (sha256 не совпал) — пришлите ещё раз');
        }
        $report = null;
        if ($image !== null && $this->images !== null) {
            [$data, $name, $report] = $this->images->prepare($data, $name, $image);
            $hash = hash('sha256', $data);
        }
        $token = bin2hex(random_bytes(16));
        $expires = time() + $this->ttl;
        $this->storage->save($token, $name, $hash, $data, $expires);
        return ['token' => $token, 'name' => $name, 'size' => strlen($data), 'sha256' => $hash,
            'expires' => date('c', $expires)] + self::describe($data, $name) + ($report !== null ? ['image' => $report] : []);
    }

    /** Имя файла по token; просрочен или не было — отказ */
    public function name(string $token): string
    {
        return $this->load($token)['name'];
    }

    /** Временный файл с содержимым по token (для процессоров, которым нужен путь) */
    public function path(string $token): string
    {
        $file = $this->load($token);
        return self::materialize($this->tmp . '/' . $token, $file['name'], $file['data']);
    }

    /** Убрать временный файл path(); принятый файл остаётся до drop или срока */
    public function cleanTemp(string $token): void
    {
        if (preg_match(self::TOKEN_RE, $token)) {
            self::removeDir($this->tmp . '/' . $token);
        }
    }

    public function drop(string $token): void
    {
        if (preg_match(self::TOKEN_RE, $token)) {
            $this->storage->drop($token);
            self::removeDir($this->tmp . '/' . $token);
        }
    }

    /** Байты во временный файл <dir>/<name>; ответ — путь */
    public static function materialize(string $dir, string $name, string $data): string
    {
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException("временный каталог не создаётся: {$dir}");
        }
        $path = $dir . '/' . self::cleanName($name);
        if (file_put_contents($path, $data) === false) {
            throw new RuntimeException("временный файл не записался: {$path}");
        }
        return $path;
    }

    /** Расширение, размеры картинки (0 — не картинка), mime */
    public static function describe(string $data, string $name): array
    {
        $info = function_exists('getimagesizefromstring') ? @getimagesizefromstring($data) : false;
        return ['ext' => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
            'width' => $info ? (int)$info[0] : 0, 'height' => $info ? (int)$info[1] : 0, 'mime' => $info['mime'] ?? ''];
    }

    /** Имя без путей и служебных символов: буквы, цифры, «.», «_», «-»; прочее → «_» */
    public static function cleanName(string $name): string
    {
        $name = (string)preg_replace('/[^\p{L}\p{N}._-]+/u', '_', basename(str_replace('\\', '/', $name)));
        return mb_substr(ltrim($name, '.'), -100);
    }

    public static function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? self::removeDir($file) : @unlink($file);
        }
        @rmdir($dir);
    }

    private function load(string $token): array
    {
        if (!preg_match(self::TOKEN_RE, $token)) {
            throw new Refusal("недопустимый token файла: {$token}");
        }
        $file = $this->storage->load($token);
        if ($file === null || (int)$file['expires'] < time()) {
            throw new Refusal('присланного файла уже нет (хранится ' . round($this->ttl / 60) . ' мин) — загрузите его заново');
        }
        return $file;
    }
}
