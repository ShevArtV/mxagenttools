<?php

namespace MxAgentTools\Tool\Batch;

class ProductSelect extends SelectTool
{
    const PRODUCTS = true;
    const NAME = 'product.select';
    const TITLE = 'Выборка товаров для пакетной правки';
    const PATH = '/agent/products/select';
    const DESCRIPTION = 'Первый шаг массовой правки товаров miniShop2 (product.prices, product.categories, resource.batch): '
        . 'фильтр (ids, section — товары раздела вместе с доп. категориями, parent, template, where, deleted) фиксируется '
        . 'на сайте выборкой. Ответ: selection, total, pages (порций по page_size), first — первые 20 id, expires.';
}
