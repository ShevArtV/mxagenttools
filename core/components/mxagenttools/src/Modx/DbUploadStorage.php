<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\UploadStorageInterface;
use RuntimeException;

/** Загрузки: учётная запись — в таблице пакета mxagenttools_upload (mxatUpload), содержимое — файл (UploadBlobs); старые записи с base64 в data читаются как раньше */
class DbUploadStorage implements UploadStorageInterface
{
    /** @var \modX */
    private $modx;
    /** @var UploadBlobs */
    private $blobs;

    public function __construct(\modX $modx, UploadBlobs $blobs)
    {
        $this->modx = $modx;
        $this->blobs = $blobs;
    }

    public function save(string $token, string $name, string $sha256, string $data, int $expires): void
    {
        $this->blobs->put($token, $data);
        $row = $this->modx->newObject('mxatUpload');
        $row->fromArray(['token' => $token, 'name' => $name, 'sha256' => $sha256, 'size' => strlen($data),
            'data' => '', 'expires' => $expires, 'createdon' => time()]);
        if (!$row->save()) {
            $this->blobs->drop($token);
            throw new RuntimeException('загрузка не сохранилась');
        }
    }

    public function load(string $token): ?array
    {
        $row = $this->modx->getObject('mxatUpload', ['token' => $token], false);
        if (!$row) {
            return null;
        }
        $data = $this->blobs->get($token);
        if ($data === null) {
            $legacy = (string)$row->get('data');
            if ($legacy === '') {
                return null;
            }
            $data = (string)base64_decode($legacy);
        }
        return ['name' => (string)$row->get('name'), 'sha256' => (string)$row->get('sha256'),
            'data' => $data, 'expires' => (int)$row->get('expires')];
    }

    public function drop(string $token): void
    {
        $this->blobs->drop($token);
        $this->modx->removeCollection('mxatUpload', ['token' => $token]);
    }

    public function purge(int $now): void
    {
        foreach ($this->modx->getIterator('mxatUpload', ['expires:<' => $now], false) as $row) {
            $this->blobs->drop((string)$row->get('token'));
        }
        $this->modx->removeCollection('mxatUpload', ['expires:<' => $now]);
    }
}
