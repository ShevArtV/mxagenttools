<?php

namespace MxAgentTools\Entity;

use MxAgentTools\Core\IdMap;
use MxAgentTools\Core\Uploads;
use MxAgentTools\Modx\GalleryFiles;
use RuntimeException;

/**
 * Галерея товара целиком; id — id товара. Снимок — оригиналы по порядку. Удаляемые записью оригиналы
 * копируются в запись журнала до записи (keep, base64) и при откате загружаются заново штатным upload:
 * у возвращённого фото новый id — замена уходит в IdMap для фото модификаций, восстанавливаемых следом.
 */
class Gallery implements EntityInterface, FileKeeperInterface
{
    /** @var GalleryFiles */
    private $files;
    /** @var IdMap */
    private $ids;
    /** @var string каталог временных файлов внутри сайта */
    private $tmp;

    public function __construct(GalleryFiles $files, IdMap $ids, string $tmp)
    {
        $this->files = $files;
        $this->ids = $ids;
        $this->tmp = rtrim($tmp, '/');
    }

    public function type(): string
    {
        return 'gallery';
    }

    public function snapshot($id): ?array
    {
        try {
            $this->files->product((int)$id);
        } catch (\MxAgentTools\Core\Refusal $e) {
            return null;
        }
        return ['product' => (int)$id, 'files' => array_values($this->files->files((int)$id))];
    }

    public function keep(array $before, ?array $after): array
    {
        $left = array_column((array)($after['files'] ?? []), 'id');
        $kept = [];
        foreach ($before['files'] as $file) {
            if (!in_array($file['id'], $left, true)) {
                // ms2 берёт название фото из имени файла — копия хранится с исходным именем
                $kept[(string)$file['id']] = ['name' => basename($file['file']),
                    'data' => base64_encode($this->files->content((int)$file['id']))];
            }
        }
        return $kept;
    }

    public function restore(array $snapshot): void
    {
        $rid = (int)$snapshot['product'];
        $kept = (array)($snapshot[self::KEPT] ?? []);
        $current = $this->files->files($this->files->product($rid)->get('id'));
        $want = [];
        foreach ($snapshot['files'] as $file) {
            $id = (int)$this->ids->map('msProductFile', $file['id']);
            if (!isset($current[$id])) {
                $copy = $kept[(string)$file['id']] ?? null;
                if (!is_array($copy) || empty($copy['data'])) {
                    throw new RuntimeException("копии фото {$file['id']} нет в журнале — вернуть его нельзя");
                }
                $dir = $this->tmp . '/restore-' . bin2hex(random_bytes(8));
                try {
                    $path = Uploads::materialize($dir, (string)$copy['name'], (string)base64_decode($copy['data']));
                    $new = $this->files->upload($rid, $path, (string)$file['description']);
                } finally {
                    Uploads::removeDir($dir);
                }
                $this->ids->set('msProductFile', $file['id'], $new);
                $id = $new;
            }
            $want[] = $id;
        }
        foreach (array_keys($current) as $id) {
            if (!in_array($id, $want, true)) {
                $this->files->remove($id);
            }
        }
        $this->files->order($rid, $want);
    }

    public function remove($id): void
    {
        throw new \LogicException('галерею целиком не удаляют — откат возвращает её состав');
    }
}
