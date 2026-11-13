<?php

namespace MxAgentTools\Image;

use MxAgentTools\Core\Refusal;

/**
 * Параметры сжатия картинки при приёме (POST /agent/uploads): max_width, max_height (0 — без ограничения),
 * quality (1–100, JPEG/WebP), format (keep | jpeg | webp), strip_metadata. Пусто — значения по умолчанию
 * (настройки mxagenttools.image_*). Без MODX, чтобы проверяться тестами.
 */
final class ImageOptions
{
    const FORMATS = ['keep', 'jpeg', 'webp'];
    const TYPES = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** @var int */
    public $maxWidth;
    /** @var int */
    public $maxHeight;
    /** @var int */
    public $quality;
    /** @var string */
    public $format;
    /** @var bool */
    public $strip;
    /** @var bool quality передан явно — перекодировать, даже если размер не меняется */
    public $explicitQuality;

    public function __construct(int $maxWidth, int $maxHeight, int $quality, string $format, bool $strip, bool $explicitQuality = false)
    {
        if ($maxWidth < 0 || $maxHeight < 0) {
            throw new Refusal('max_width / max_height: 0 или больше');
        }
        if ($quality < 1 || $quality > 100) {
            throw new Refusal('quality: от 1 до 100');
        }
        if (!in_array($format, self::FORMATS, true)) {
            throw new Refusal('format: ' . implode(' | ', self::FORMATS));
        }
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
        $this->quality = $quality;
        $this->format = $format;
        $this->strip = $strip;
        $this->explicitQuality = $explicitQuality;
    }

    /** Параметры запроса поверх умолчаний: $defaults — max_side, quality, format, strip */
    public static function from(array $args, array $defaults): self
    {
        $side = (int)($defaults['max_side'] ?? 2000);
        return new self(isset($args['max_width']) ? (int)$args['max_width'] : $side,
            isset($args['max_height']) ? (int)$args['max_height'] : $side,
            isset($args['quality']) ? (int)$args['quality'] : (int)($defaults['quality'] ?? 82),
            isset($args['format']) && $args['format'] !== '' ? (string)$args['format'] : (string)($defaults['format'] ?? 'keep'),
            array_key_exists('strip_metadata', $args) ? (bool)$args['strip_metadata'] : (bool)($defaults['strip'] ?? true),
            isset($args['quality']));
    }

    /** null — original=true: Uploads::put сохранит байты как есть, ключи сжатия игнорируются; иначе from() */
    public static function forUpload(array $args, array $defaults): ?self
    {
        return filter_var($args['original'] ?? false, FILTER_VALIDATE_BOOLEAN) ? null : self::from($args, $defaults);
    }

    /** Размер после вписывания в max_width × max_height (без увеличения) */
    public function fit(int $w, int $h): array
    {
        $scale = 1.0;
        if ($this->maxWidth > 0 && $w > $this->maxWidth) {
            $scale = min($scale, $this->maxWidth / $w);
        }
        if ($this->maxHeight > 0 && $h > $this->maxHeight) {
            $scale = min($scale, $this->maxHeight / $h);
        }
        return $scale >= 1.0 ? [$w, $h] : [max(1, (int)round($w * $scale)), max(1, (int)round($h * $scale))];
    }

    /** Итоговое расширение: keep — как было (jpeg → jpg) */
    public function ext(string $ext): string
    {
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        return $this->format === 'keep' ? $ext : ($this->format === 'jpeg' ? 'jpg' : 'webp');
    }
}
