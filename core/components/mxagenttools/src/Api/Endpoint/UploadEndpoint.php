<?php

namespace MxAgentTools\Api\Endpoint;

use MxAgentTools\Api\Passport;
use MxAgentTools\Image\ImageOptions;

class UploadEndpoint extends AgentEndpoint
{
    protected function describe()
    {
        $spec = Passport::endpoint('agent.uploads.create', 'Принять файл',
            'Принять файл для инструментов записи (gallery.upload): содержимое base64 в теле, имя и sha256 для сверки. '
            . 'Картинка (JPEG, PNG, WebP, GIF без анимации) сразу ужимается: поворот по EXIF, вписывание в max_width × max_height '
            . '(без увеличения), quality для JPEG/WebP, format keep | jpeg | webp, strip_metadata — снять EXIF/GPS. Параметры '
            . 'сжатия для сайта — из инструкции сайта; не заданы — умолчания пакета (mxagenttools.image_max_side 2000, '
            . 'image_quality 82, image_format keep, image_strip_metadata 1). SVG и не-картинки — как есть; уже малая '
            . 'картинка не перекодируется. Ответ: token (живёт mxagenttools.upload_ttl секунд), name (расширение — по '
            . 'итоговому формату), size, sha256 — ужатого, ext, width/height, mime и image {engine, before {size, width, height}, '
            . 'after {…}, processed, reason}. Размер присланного — до mxagenttools.upload_max_mb МиБ. Сам по себе файл на сайт '
            . 'не попадает.',
            '/agent/uploads', true, [
                ['name' => 'data', 'type' => 'string', 'required' => true, 'description' => 'Содержимое файла в base64.',
                    'example' => '/9j/4AAQSkZJRgABAQ…'],
                ['name' => 'name', 'type' => 'string', 'required' => true,
                    'description' => 'Имя файла с расширением — из него ms2 возьмёт название фото.', 'example' => 'blue-dress-front.jpg'],
                ['name' => 'sha256', 'type' => 'string', 'description' => 'sha256 присланного содержимого (hex) — сверка целостности.',
                    'example' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'],
                ['name' => 'max_width', 'type' => 'integer', 'min' => 0, 'description' => 'Ширина не больше, px (0 — без ограничения; '
                    . 'по умолчанию mxagenttools.image_max_side).', 'example' => 2000],
                ['name' => 'max_height', 'type' => 'integer', 'min' => 0, 'description' => 'Высота не больше, px (0 — без ограничения).',
                    'example' => 2000],
                ['name' => 'quality', 'type' => 'integer', 'min' => 1, 'max' => 100, 'description' => 'Качество JPEG/WebP 1–100 '
                    . '(по умолчанию mxagenttools.image_quality).', 'example' => 82],
                ['name' => 'format', 'type' => 'string', 'enum' => ['keep', 'jpeg', 'webp'], 'description' => 'Итоговый формат: keep — '
                    . 'как прислан, jpeg, webp.', 'example' => 'keep'],
                ['name' => 'strip_metadata', 'type' => 'boolean', 'description' => 'Снять EXIF/GPS и комментарии (по умолчанию да).'],
            ]);
        // POST со scope agent.write, но не write-эндпоинт mxApi: иначе журнал вызовов и идемпотентность mxApi
        // сохраняли бы тело — файл в base64 — целиком. Запись на сайт делает инструмент, он и журналируется.
        $spec['write'] = false;
        return $spec;
    }

    protected function run(array $params): array
    {
        return $this->toolbox->uploads()->put((string)$params['data'], (string)$params['name'], (string)($params['sha256'] ?? ''),
            ImageOptions::from($params, $this->toolbox->imageDefaults()));
    }
}
