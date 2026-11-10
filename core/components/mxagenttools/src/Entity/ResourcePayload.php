<?php

namespace MxAgentTools\Entity;

use MxAgentTools\Modx\ProductData;
use MxAgentTools\Modx\TemplateVars;

/**
 * Данные для resource/update|create из снимка: полный набор, как при «Сохранить» в админке.
 * Процессор обновления пишет все TV шаблона из запроса (непереданный — сбросит в умолчание), поэтому
 * передаются все поля и все TV, а не только изменённые.
 */
class ResourcePayload
{
    /** Поля, которые процессору не передаются: служебные, корзина (resource/delete|undelete) */
    const SKIP = ['deleted', 'uri'];

    /** @var TemplateVars */
    private $tvs;
    /** @var ProductData|null */
    private $product;

    public function __construct(TemplateVars $tvs, ?ProductData $product)
    {
        $this->tvs = $tvs;
        $this->product = $product;
    }

    /** $target — снимок «станет», $current — текущий (для опций и категорий товара: передаются, только если меняются) */
    public function build(array $target, array $current): array
    {
        $fields = (array)$target['fields'];
        $data = [];
        foreach ($fields as $k => $v) {
            // uri с uri_override=0 процессор считает сам; свой uri — только при uri_override
            if (!in_array($k, self::SKIP, true) || $k === 'uri' && ($fields['uri_override'] ?? '0') === '1') {
                $data[$k] = $v;
            }
        }
        if (isset($target['id'])) {
            $data['id'] = $target['id'];
        }
        $data['class_key'] = $target['class_key'] ?? ($fields['class_key'] ?? 'modDocument');
        $tvs = (array)($target['tvs'] ?? []);
        if ($tvs) {
            $data['tvs'] = 1;
            // при смене шаблона TV прежнего, которых нет в новом, процессор не пишет — и не передаём;
            // TV нового шаблона получат умолчания (задать их — следующим resource.update)
            foreach (array_intersect_key($this->tvs->ids((int)($fields['template'] ?? 0)), $tvs) as $name => $id) {
                $data['tv' . $id] = $tvs[$name];
            }
        }
        if ($this->product && isset($target['product'])) {
            $data = array_merge($data, $this->product->payload($target, $current));
        }
        // resource/update без syncsite кэш не чистит (setCheckbox ставит false)
        $data['syncsite'] = 1;
        return $data;
    }
}
