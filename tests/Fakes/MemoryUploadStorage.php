<?php

namespace MxAgentTools\Tests\Fakes;

use MxAgentTools\Core\UploadStorageInterface;

class MemoryUploadStorage implements UploadStorageInterface
{
    /** @var array */
    public $files = [];

    public function save(string $token, string $name, string $sha256, string $data, int $expires): void
    {
        $this->files[$token] = ['name' => $name, 'sha256' => $sha256, 'data' => $data, 'expires' => $expires];
    }

    public function load(string $token): ?array
    {
        return $this->files[$token] ?? null;
    }

    public function drop(string $token): void
    {
        unset($this->files[$token]);
    }

    public function purge(int $now): void
    {
        $this->files = array_filter($this->files, function (array $f) use ($now) {
            return $f['expires'] >= $now;
        });
    }
}
