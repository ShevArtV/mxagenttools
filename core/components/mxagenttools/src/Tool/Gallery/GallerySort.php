<?php

namespace MxAgentTools\Tool\Gallery;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Modx\GalleryFiles;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

class GallerySort extends AbstractTool implements WriteToolInterface
{
    const NAME = 'gallery.sort';
    const TITLE = 'Порядок галереи';
    const PATH = '/agent/products/{product:\\d+}/gallery/sort';
    const KIND = self::WRITE;
    const DESCRIPTION = 'Порядок галереи товара: order — все id фото в новом порядке (первое — главное фото товара). '
        . 'Пишется процессором ms2 gallery/sort по одному фото на место.';

    /** @var GalleryFiles */
    private $files;

    public function __construct(GalleryFiles $files)
    {
        $this->files = $files;
    }

    protected function args(): array
    {
        return self::obj(['product' => self::int('id товара'), 'order' => self::ids('все id фото в новом порядке')],
            ['product', 'order']);
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
        $byId = array_column($after['files'], null, 'id');
        $ranks = array_column($after['files'], 'rank');
        sort($ranks);
        $after['files'] = [];
        foreach (Position::permutation(array_keys($byId), Values::items($args, 'order')) as $i => $id) {
            // sort переносит rank на место: набор рангов тот же, раздаётся по новому порядку
            $after['files'][] = ['rank' => $ranks[$i]] + $byId[$id];
        }
        return ["gallery:{$rid}" => $after];
    }

    public function apply(array $args): array
    {
        $rid = Values::id($args, 'product');
        $order = Position::permutation(array_keys($this->files->files($rid)), Values::items($args, 'order'));
        $this->files->order($rid, $order);
        $this->files->refresh();
        return [];
    }
}
