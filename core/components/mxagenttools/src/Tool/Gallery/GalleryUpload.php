<?php

namespace MxAgentTools\Tool\Gallery;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Uploads;
use MxAgentTools\Core\Values;
use MxAgentTools\Modx\GalleryFiles;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

class GalleryUpload extends AbstractTool implements WriteToolInterface
{
    const NAME = 'gallery.upload';
    const TITLE = 'Загрузить фото в галерею';
    const PATH = '/agent/products/{product:\\d+}/gallery';
    const KIND = self::WRITE;
    const DESCRIPTION = 'Загрузить файл (token из POST /agent/uploads) в галерею товара процессором ms2 gallery/upload (превью — штатно) '
        . 'и поставить на позицию first|last|N. Название фото ms2 берёт из имени файла. Откат удаляет загруженное.';

    /** @var GalleryFiles */
    private $files;
    /** @var Uploads */
    private $uploads;

    public function __construct(GalleryFiles $files, Uploads $uploads)
    {
        $this->files = $files;
        $this->uploads = $uploads;
    }

    protected function args(): array
    {
        return self::obj(['product' => self::int('id товара'), 'file' => self::str('token из ответа POST /agent/uploads', '0f3c…'),
            'description' => self::str('описание фото'), 'position' => self::str('first | last | N, по умолчанию last')],
            ['product', 'file']);
    }

    public function targets(array $args): array
    {
        return [new EntityRef('gallery', Values::id($args, 'product'))];
    }

    public function preview(array $args, array $before): array
    {
        $rid = Values::id($args, 'product');
        $after = $before["gallery:{$rid}"];
        if ($after === null) {
            throw new Refusal("товара {$rid} нет");
        }
        $name = $this->check($rid, Values::text($args, 'file', true));
        $new = ['id' => 0, 'name' => strtolower(pathinfo($name, PATHINFO_FILENAME)), 'description' => Values::text($args, 'description'),
            'file' => $name];
        $after['files'] = Position::insert($after['files'], $new, $args['position'] ?? 'last');
        return ["gallery:{$rid}" => $after];
    }

    public function apply(array $args): array
    {
        $rid = Values::id($args, 'product');
        $token = Values::text($args, 'file', true);
        $ids = array_keys($this->files->files($rid));
        $this->check($rid, $token);
        try {
            $new = $this->files->upload($rid, $this->uploads->path($token), Values::text($args, 'description'));
        } finally {
            $this->uploads->cleanTemp($token);
        }
        $this->uploads->drop($token);
        $this->files->order($rid, Position::insert(array_values(array_diff($ids, [$new])), $new, $args['position'] ?? 'last'));
        $this->files->refresh();
        return [];
    }

    /** Файл есть и его расширение принимает медиаисточник галереи (иначе upload упал бы с невнятной ошибкой); ответ — имя */
    private function check(int $rid, string $token): string
    {
        $name = $this->uploads->name($token);
        $accepts = $this->files->accepts($rid);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($accepts['extensions'] && !in_array($ext, $accepts['extensions'], true)) {
            throw new Refusal("галерея товара не принимает .{$ext} — можно: " . implode(', ', $accepts['extensions']));
        }
        return $name;
    }
}
