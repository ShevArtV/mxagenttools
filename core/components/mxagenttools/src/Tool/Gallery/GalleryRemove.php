<?php

namespace MxAgentTools\Tool\Gallery;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\Values;
use MxAgentTools\Modx\GalleryFiles;
use MxAgentTools\Modx\Modifications;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

/**
 * Удаление фото галереи. Модификации, у которых оно есть, задеты тоже: фото снимается с них процессором
 * msOptionsPrice2 до удаления из галереи, чтобы не осталось ссылок на несуществующий файл. Оригинал до записи
 * копируется в журнал (Gallery::keep) — откат загружает его заново и возвращает модификациям.
 */
class GalleryRemove extends AbstractTool implements WriteToolInterface
{
    const NAME = 'gallery.remove';
    const TITLE = 'Удалить фото из галереи';
    const PATH = '/agent/gallery/{id:\\d+}/delete';
    const KIND = self::WRITE;
    const DESCRIPTION = 'Удалить фото из галереи товара (ms2 gallery/remove: оригинал и превью с диска); с модификаций оно '
        . 'снимается. Копия оригинала — в журнале, откат возвращает фото (с новым id) на прежнее место и модификациям.';

    /** @var GalleryFiles */
    private $files;
    /** @var Modifications|null */
    private $mods;

    public function __construct(GalleryFiles $files, ?Modifications $mods)
    {
        $this->files = $files;
        $this->mods = $mods;
    }

    protected function args(): array
    {
        return self::obj(['id' => self::int('id фото галереи')], ['id']);
    }

    public function targets(array $args): array
    {
        $id = Values::id($args, 'id');
        $rid = (int)$this->files->file($id)->get('product_id');
        $refs = [new EntityRef('gallery', $rid)];
        foreach ($this->mods ? $this->mods->withImage($rid, $id) : [] as $mid) {
            $refs[] = new EntityRef('modification', $mid);
        }
        return $refs;
    }

    public function preview(array $args, array $before): array
    {
        $id = Values::id($args, 'id');
        $after = [];
        foreach ($before as $key => $snapshot) {
            if (strpos($key, 'gallery:') === 0) {
                $snapshot['files'] = array_values(array_filter($snapshot['files'], function (array $f) use ($id) {
                    return $f['id'] !== $id;
                }));
            } else {
                $snapshot['images'] = self::without($snapshot['images'], $id);
                $snapshot['fields']['image'] = (string)($snapshot['images'][0][0] ?? 0);
            }
            $after[$key] = $snapshot;
        }
        return $after;
    }

    public function apply(array $args): array
    {
        $id = Values::id($args, 'id');
        $rid = (int)$this->files->file($id)->get('product_id');
        foreach ($this->mods ? $this->mods->withImage($rid, $id) : [] as $mid) {
            $this->mods->setImages($mid, array_column(self::without($this->mods->images($mid), $id), 0));
        }
        $this->files->remove($id);
        $this->files->refresh();
        return [];
    }

    /** Фото модификации без $id, ранги заново с 0 — так их пересоберёт setImages */
    private static function without(array $pairs, int $id): array
    {
        $out = [];
        foreach ($pairs as $pair) {
            if ((int)$pair[0] !== $id) {
                $out[] = [(int)$pair[0], count($out)];
            }
        }
        return $out;
    }
}
