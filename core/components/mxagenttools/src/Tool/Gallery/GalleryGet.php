<?php

namespace MxAgentTools\Tool\Gallery;

use MxAgentTools\Core\Values;
use MxAgentTools\Modx\GalleryFiles;
use MxAgentTools\Modx\Modifications;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class GalleryGet extends AbstractTool implements ReadToolInterface
{
    const NAME = 'gallery.get';
    const TITLE = 'Галерея товара';
    const PATH = '/agent/products/{product:\\d+}/gallery';
    const DESCRIPTION = 'Галерея товара ms2 по порядку (id, имя, описание, тип, адрес), что принимает медиаисточник, '
        . 'и (если есть msOptionsPrice2) фото модификаций товара с рангами.';

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
        return self::obj(['product' => self::int('id товара')], ['product']);
    }

    public function read(array $args): array
    {
        $rid = Values::id($args, 'product');
        $product = $this->files->product($rid);
        $out = ['product' => $rid, 'pagetitle' => $product->get('pagetitle'), 'files' => array_values($this->files->files($rid)),
            'accepts' => $this->files->accepts($rid)];
        if ($this->mods) {
            foreach ($this->mods->ofProduct($rid) as $mid) {
                $out['modifications'][] = ['id' => $mid, 'images' => $this->mods->images($mid)];
            }
        }
        return $out;
    }
}
