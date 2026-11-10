<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\UploadStorageInterface;
use RuntimeException;

/** Загрузки в таблице пакета mxagenttools_upload (mxatUpload): содержимое — base64; служебная таблица — объектами xPDO */
class DbUploadStorage implements UploadStorageInterface
{
    /** @var \modX */
    private $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    public function save(string $token, string $name, string $sha256, string $data, int $expires): void
    {
        $row = $this->modx->newObject('mxatUpload');
        $row->fromArray(['token' => $token, 'name' => $name, 'sha256' => $sha256, 'size' => strlen($data),
            'data' => base64_encode($data), 'expires' => $expires, 'createdon' => time()]);
        if (!$row->save()) {
            throw new RuntimeException('загрузка не сохранилась');
        }
    }

    public function load(string $token): ?array
    {
        $row = $this->modx->getObject('mxatUpload', ['token' => $token], false);
        if (!$row) {
            return null;
        }
        return ['name' => (string)$row->get('name'), 'sha256' => (string)$row->get('sha256'),
            'data' => (string)base64_decode((string)$row->get('data')), 'expires' => (int)$row->get('expires')];
    }

    public function drop(string $token): void
    {
        $this->modx->removeCollection('mxatUpload', ['token' => $token]);
    }

    public function purge(int $now): void
    {
        $this->modx->removeCollection('mxatUpload', ['expires:<' => $now]);
    }
}
