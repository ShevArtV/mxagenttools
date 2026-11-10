<?php

namespace MxAgentTools\Image;

/** Сжатие картинки одним движком (Imagick или GD) */
interface ImageProcessorInterface
{
    /**
     * @param string $ext исходное расширение (jpg, png, webp, gif)
     * @return array|null [данные, расширение, ширина, высота] или null — оставить файл как есть (анимация, не прочиталось)
     */
    public function process(string $data, string $ext, ImageOptions $options): ?array;

    public function name(): string;
}
