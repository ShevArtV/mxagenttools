<?php

namespace MxAgentTools\Image;

/** Imagick: авто-поворот по EXIF, вписывание, снятие метаданных, JPEG/WebP с качеством; анимация — без обработки */
class ImagickProcessor implements ImageProcessorInterface
{
    public function name(): string
    {
        return 'imagick';
    }

    public function process(string $data, string $ext, ImageOptions $options): ?array
    {
        $im = new \Imagick();
        try {
            $im->readImageBlob($data);
        } catch (\ImagickException $e) {
            return null;
        }
        if ($im->getNumberImages() > 1) {
            return null;
        }
        $this->orient($im);
        [$w, $h] = $options->fit($im->getImageWidth(), $im->getImageHeight());
        if ($w !== $im->getImageWidth() || $h !== $im->getImageHeight()) {
            $im->resizeImage($w, $h, \Imagick::FILTER_LANCZOS, 1);
        }
        if ($options->strip) {
            $profiles = $im->getImageProfiles('icc', true);
            $im->stripImage();
            if (!empty($profiles['icc'])) {
                $im->profileImage('icc', $profiles['icc']); // цвета без ICC поехали бы
            }
        }
        $out = $options->ext($ext);
        if ($out === 'jpg' && $im->getImageAlphaChannel()) {
            $im->setImageBackgroundColor('white');
            $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        }
        $im->setImageFormat($out === 'jpg' ? 'jpeg' : $out);
        if (in_array($out, ['jpg', 'webp'], true)) {
            $im->setImageCompressionQuality($options->quality);
        }
        if ($out === 'jpg') {
            $im->setInterlaceScheme(\Imagick::INTERLACE_JPEG);
        }
        return [$im->getImageBlob(), $out, $im->getImageWidth(), $im->getImageHeight()];
    }

    private function orient(\Imagick $im): void
    {
        if (method_exists($im, 'autoOrient')) {
            $im->autoOrient();
            return;
        }
        switch ($im->getImageOrientation()) {
            case \Imagick::ORIENTATION_BOTTOMRIGHT:
                $im->rotateImage('#000', 180);
                break;
            case \Imagick::ORIENTATION_RIGHTTOP:
                $im->rotateImage('#000', 90);
                break;
            case \Imagick::ORIENTATION_LEFTBOTTOM:
                $im->rotateImage('#000', -90);
                break;
        }
        $im->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }
}
