<?php

namespace MxAgentTools\Image;

/** GD: поворот по EXIF (exif_read_data), вписывание, JPEG/WebP/PNG; метаданные GD не переносит никогда */
class GdProcessor implements ImageProcessorInterface
{
    public function name(): string
    {
        return 'gd';
    }

    public function process(string $data, string $ext, ImageOptions $options): ?array
    {
        if ($ext === 'gif' && substr_count($data, "\x21\xF9\x04") > 1) {
            return null; // анимация
        }
        $src = @imagecreatefromstring($data);
        if (!$src) {
            return null;
        }
        $src = $this->orient($src, $data, $ext);
        [$w, $h] = $options->fit(imagesx($src), imagesy($src));
        $out = $options->ext($ext);
        if ($w !== imagesx($src) || $h !== imagesy($src) || $out === 'jpg') {
            $dst = imagecreatetruecolor($w, $h);
            if ($out === 'jpg') {
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            } else {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
            imagedestroy($src);
            $src = $dst;
        }
        ob_start();
        if ($out === 'jpg') {
            imageinterlace($src, true);
            imagejpeg($src, null, $options->quality);
        } elseif ($out === 'webp' && function_exists('imagewebp')) {
            imagewebp($src, null, $options->quality);
        } elseif ($out === 'png') {
            imagepng($src, null, 9);
        } elseif ($out === 'gif') {
            imagegif($src);
        } else {
            ob_end_clean();
            imagedestroy($src);
            return null;
        }
        $blob = (string)ob_get_clean();
        imagedestroy($src);
        return [$blob, $out, $w, $h];
    }

    /** @param resource|\GdImage $img */
    private function orient($img, string $data, string $ext)
    {
        if (!in_array($ext, ['jpg', 'jpeg'], true) || !function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($data));
        $angle = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
        return $angle ? imagerotate($img, $angle, 0) : $img;
    }
}
