<?php

namespace MxAgentTools\Api\Endpoint;

use MxAgentTools\Api\Passport;

class UploadEndpoint extends AgentEndpoint
{
    protected function describe()
    {
        $spec = Passport::endpoint('agent.uploads.create', 'Принять файл',
            'Принять файл для инструментов записи (gallery.upload): содержимое base64 в теле, имя и sha256 для сверки. '
            . 'Ответ: token (живёт mxagenttools.upload_ttl секунд, по умолчанию час), name, size, sha256, ext, width/height '
            . '(0 — не картинка), mime. Размер — до mxagenttools.upload_max_mb МиБ. Сам по себе файл на сайт не попадает.',
            '/agent/uploads', true, [
                ['name' => 'data', 'type' => 'string', 'required' => true, 'description' => 'Содержимое файла в base64.',
                    'example' => '/9j/4AAQSkZJRgABAQ…'],
                ['name' => 'name', 'type' => 'string', 'required' => true,
                    'description' => 'Имя файла с расширением — из него ms2 возьмёт название фото.', 'example' => 'blue-dress-front.jpg'],
                ['name' => 'sha256', 'type' => 'string', 'description' => 'sha256 содержимого (hex) — сверка целостности.',
                    'example' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'],
            ]);
        // POST со scope agent.write, но не write-эндпоинт mxApi: иначе журнал вызовов и идемпотентность mxApi
        // сохраняли бы тело — файл в base64 — целиком. Запись на сайт делает инструмент, он и журналируется.
        $spec['write'] = false;
        return $spec;
    }

    protected function run(array $params): array
    {
        return $this->toolbox->uploads()->put((string)$params['data'], (string)$params['name'], (string)($params['sha256'] ?? ''));
    }
}
