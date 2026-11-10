<?php

namespace MxAgentTools\Tool\Modification;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Modx\GalleryFiles;
use MxAgentTools\Modx\Modifications;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

/** Общее для записей существующей модификации: id из пути, снимок «было», сброс кэша после записи */
abstract class ModificationWrite extends AbstractTool implements WriteToolInterface
{
    const KIND = self::WRITE;

    /** @var Modifications */
    protected $mods;
    /** @var GalleryFiles */
    protected $files;

    public function __construct(Modifications $mods, GalleryFiles $files)
    {
        $this->mods = $mods;
        $this->files = $files;
    }

    public function targets(array $args): array
    {
        return [new EntityRef('modification', Values::id($args, 'id'))];
    }

    protected function before(array $args, array $before): array
    {
        $mid = Values::id($args, 'id');
        if ($before["modification:{$mid}"] === null) {
            throw new Refusal("модификации {$mid} нет");
        }
        return $before["modification:{$mid}"];
    }

    protected function current(array $args): array
    {
        $mid = Values::id($args, 'id');
        $snapshot = $this->mods->snapshot($mid);
        if ($snapshot === null) {
            throw new Refusal("модификации {$mid} нет");
        }
        return $snapshot;
    }

    /** Фото — из галереи товара модификации и картинки */
    protected function checkImages(int $rid, array $ids): void
    {
        $gallery = $this->files->files($rid);
        foreach ($ids as $id) {
            if (!isset($gallery[(int)$id]) || $gallery[(int)$id]['type'] !== 'image') {
                throw new Refusal("фото {$id} нет в галерее товара {$rid} (или это не картинка)");
            }
        }
    }
}
