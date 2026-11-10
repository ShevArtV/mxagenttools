<?php

namespace MxAgentTools\Tool\Batch;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;

class ProductCategories extends ResourceBatchTool
{
    const NAME = 'product.categories';
    const TITLE = 'Доп. категории товаров массово';
    const PATH = '/agent/products/categories';
    const DESCRIPTION = 'Добавить (add) и/или убрать (remove) дополнительные категории miniShop2 у многих товаров одним планом '
        . '— «добавить товары в подборку», «убрать из раздела». Остальные доп. категории товара не трогаются; родитель — не '
        . 'доп. категория (перенос — resource.batch с parent). Объекты — ids или selection + page из product.select.';

    protected function changeArgs(): array
    {
        return [
            'add' => self::ids('id разделов: добавить доп. категорией', [416]),
            'remove' => self::ids('id разделов: снять как доп. категорию', [43]),
        ];
    }

    protected function change(array $snapshot, array $args): array
    {
        if (!isset($snapshot['categories'])) {
            throw new Refusal("ресурс {$snapshot['id']} — не товар miniShop2");
        }
        $add = array_map('intval', Values::items($args, 'add'));
        $remove = array_map('intval', Values::items($args, 'remove'));
        if (!$add && !$remove) {
            throw new Refusal('нужен add и/или remove — id разделов');
        }
        if (array_intersect($add, $remove)) {
            throw new Refusal('раздел и в add, и в remove');
        }
        $add = array_diff($add, [(int)$snapshot['fields']['parent']]); // родитель доп. категорией не бывает
        $ids = array_values(array_unique(array_diff(array_merge($snapshot['categories'], $add), $remove)));
        sort($ids);
        $snapshot['categories'] = $ids;
        return $snapshot;
    }
}
