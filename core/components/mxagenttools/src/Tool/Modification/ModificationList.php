<?php

namespace MxAgentTools\Tool\Modification;

use MxAgentTools\Core\Values;
use MxAgentTools\Modx\GalleryFiles;
use MxAgentTools\Modx\Modifications;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class ModificationList extends AbstractTool implements ReadToolInterface
{
    const NAME = 'modification.list';
    const TITLE = 'Модификации товара';
    const PATH = '/agent/products/{product:\\d+}/modifications';
    const DESCRIPTION = 'Все модификации msOptionsPrice2 товара по порядку: поля (price, old_price, count, active, name, '
        . 'article, type, rank, image), опции {ключ: [значения]}, фото [[id фото галереи, rank]]; плюс опции с mid=0 '
        . '(недосохранённые — мешают созданию модификации).';

    /** @var Modifications */
    private $mods;
    /** @var GalleryFiles */
    private $files;

    public function __construct(Modifications $mods, GalleryFiles $files)
    {
        $this->mods = $mods;
        $this->files = $files;
    }

    protected function args(): array
    {
        return self::obj(['product' => self::int('id товара', 25)], ['product']);
    }

    public function read(array $args): array
    {
        $rid = Values::id($args, 'product');
        $this->files->product($rid);
        $out = [];
        foreach ($this->mods->ofProduct($rid) as $mid) {
            $out[] = $this->mods->snapshot($mid);
        }
        return ['product' => $rid, 'modifications' => $out, 'pending_options' => $this->mods->pending($rid)];
    }
}
