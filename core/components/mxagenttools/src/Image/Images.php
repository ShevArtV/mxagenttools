<?php

namespace MxAgentTools\Image;

/**
 * Сжатие принятой картинки: движок — Imagick, если есть, иначе GD; нет ни того ни другого, не картинка, SVG или
 * анимация — файл как есть. Перекодируется, только если есть что менять (размер, формат, поворот, явный quality,
 * метаданные); результат тяжелее исходника без смены размера и формата — остаётся исходник.
 */
class Images
{
    /** @var ImageProcessorInterface|null */
    private $processor;

    public function __construct(?ImageProcessorInterface $processor = null)
    {
        $this->processor = $processor ?? self::detect();
    }

    public static function detect(): ?ImageProcessorInterface
    {
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            return new ImagickProcessor();
        }
        return function_exists('imagecreatefromstring') ? new GdProcessor() : null;
    }

    /** @return array [данные, имя, отчёт {engine, before:{size,width,height}, after:{…}, processed, reason}] */
    public function prepare(string $data, string $name, ImageOptions $options): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $info = @getimagesizefromstring($data);
        $before = ['size' => strlen($data), 'width' => $info ? (int)$info[0] : 0, 'height' => $info ? (int)$info[1] : 0];
        $report = ['engine' => $this->processor ? $this->processor->name() : '', 'before' => $before, 'after' => $before,
            'processed' => false];
        if (!$info || !in_array($ext, ImageOptions::TYPES, true)) {
            return [$data, $name, $report + ['reason' => 'не картинка JPEG/PNG/WebP/GIF — без обработки']];
        }
        if (!$this->processor) {
            return [$data, $name, $report + ['reason' => 'на сервере нет Imagick и GD']];
        }
        $out = $options->ext($ext);
        [$w, $h] = $options->fit($before['width'], $before['height']);
        $resize = $w !== $before['width'] || $h !== $before['height'];
        $convert = $out !== ($ext === 'jpeg' ? 'jpg' : $ext);
        $rotated = $this->orientation($data, $ext) > 1;
        $metadata = $options->strip && $this->hasMetadata($data, $ext);
        if (!$resize && !$convert && !$rotated && !$metadata && !$options->explicitQuality) {
            return [$data, $name, $report + ['reason' => 'уже в пределах — без изменений']];
        }
        $res = $this->processor->process($data, $ext, $options);
        if ($res === null) {
            return [$data, $name, $report + ['reason' => 'анимация или не прочиталась — без обработки']];
        }
        [$blob, $newExt, $nw, $nh] = $res;
        if (!$resize && !$convert && !$rotated && strlen($blob) >= strlen($data)) {
            return [$data, $name, $report + ['reason' => 'перекодирование не уменьшило файл — оставлен исходник']];
        }
        $newName = pathinfo($name, PATHINFO_FILENAME) . '.' . $newExt;
        return [$blob, $newName, ['engine' => $this->processor->name(), 'before' => $before,
            'after' => ['size' => strlen($blob), 'width' => (int)$nw, 'height' => (int)$nh], 'processed' => true]];
    }

    private function orientation(string $data, string $ext): int
    {
        if (!in_array($ext, ['jpg', 'jpeg'], true) || !function_exists('exif_read_data')) {
            return 1;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($data));
        return (int)($exif['Orientation'] ?? 1);
    }

    /** EXIF/XMP/комментарии в JPEG (APP1, COM) или eXIf/tEXt в PNG — есть что снимать */
    private function hasMetadata(string $data, string $ext): bool
    {
        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            return strpos(substr($data, 0, 65536), "\xFF\xE1") !== false || strpos(substr($data, 0, 65536), "\xFF\xFE") !== false;
        }
        if ($ext === 'png') {
            return strpos($data, 'eXIf') !== false || strpos($data, 'tEXt') !== false || strpos($data, 'iTXt') !== false;
        }
        return false;
    }
}
