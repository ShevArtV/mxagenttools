<?php

namespace MxAgentTools\Tool\Link;

use MxAgentTools\Core\LinkPairs;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Tool\Batch\BatchIds;
use MxAgentTools\Tool\BatchToolInterface;
use MxAgentTools\Modx\ProductLinks;

class ProductLinkSet extends LinkTool implements BatchToolInterface
{
    const NAME = 'product.link.set';
    const TITLE = 'Связать набор товаров каждый с каждым';
    const PATH = '/agent/products/links/set';
    const DESCRIPTION = 'Связать товары набора каждый с каждым по типу link (many_to_many/one_to_one) одним планом — '
        . '«цвета модели»: набор из N товаров даёт N·(N−1) строк, на каждой карточке кружки остальных. Недостающие строки '
        . 'добавляются, готовые не трогаются: повторный apply дублей не создаёт, и можно досвязать новый цвет, передав всю '
        . 'модель целиком. Лишнего не удаляет. Объекты — ids или selection + page из product.select (одна порция) и '
        . 'batch — id операции клиента; в наборе не больше %d товаров. Откат — по записи журнала или '
        . 'POST /agent/journal/batch/{batch}/rollback.';
    const MAX = 40;

    /** @var BatchIds */
    private $ids;

    public function __construct(ProductLinks $links, BatchIds $ids)
    {
        parent::__construct($links);
        $this->ids = $ids;
    }

    public function describe(): array
    {
        $out = parent::describe();
        $out['description'] = sprintf($out['description'], self::MAX);
        return $out;
    }

    protected function args(): array
    {
        return self::obj(BatchIds::args() + ['link' => self::linkArg()]);
    }

    protected function present(): bool
    {
        return true;
    }

    protected function rows(array $args): array
    {
        $ids = $this->ids->ids($args);
        if (count($ids) > self::MAX) {
            throw new Refusal('в наборе ' . count($ids) . ' товаров, максимум ' . self::MAX . ' — модель не такая большая, сверьте выборку');
        }
        foreach ($ids as $id) {
            $this->links->title($id);
        }
        return LinkPairs::group($this->kindOf($args), $ids);
    }
}
