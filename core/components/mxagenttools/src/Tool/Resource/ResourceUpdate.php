<?php

namespace MxAgentTools\Tool\Resource;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Entity\Resource;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

class ResourceUpdate extends AbstractTool implements WriteToolInterface
{
    const NAME = 'resource.update';
    const TITLE = 'Изменить ресурс';
    const PATH = '/agent/resources/{id:\\d+}';
    const KIND = self::WRITE;
    const DESCRIPTION = 'Правка ресурса как «Сохранить» в админке: поля (pagetitle, alias, parent, published, content, template…), '
        . 'TV по имени, у товара — данные (price, article…), опции {ключ: [значения]} и доп. категории (полный список id). '
        . 'Пишется resource/update процессором класса ресурса полным набором полей и TV. Привязки @EVAL/@FILE/@SELECT/'
        . '@CHUNK/@SNIPPET в TV запрещены. При смене template TV нового шаблона получают умолчания — задайте их '
        . 'следующим resource.update. Снимок «было» — resource.get.';

    /** @var Resource */
    private $resource;

    public function __construct(Resource $resource)
    {
        $this->resource = $resource;
    }

    protected function args(): array
    {
        return self::obj([
            'id' => self::int('id ресурса'),
            'fields' => self::map('поле => значение'),
            'tvs' => self::map('имя TV => значение (список — склеивается «||»)'),
            'product' => self::map('поле товара ms2 => значение'),
            'options' => self::map('опция товара ms2 => список значений; [] — снять опцию'),
            'categories' => self::ids('доп. категории товара — полный список'),
        ], ['id']);
    }

    public function targets(array $args): array
    {
        return [new EntityRef('resource', Values::id($args, 'id'))];
    }

    public function preview(array $args, array $before): array
    {
        $key = 'resource:' . Values::id($args, 'id');
        if ($before[$key] === null) {
            throw new Refusal('ресурса ' . Values::id($args, 'id') . ' нет');
        }
        return [$key => ResourceChange::merge($before[$key], $args)];
    }

    public function apply(array $args): array
    {
        $id = Values::id($args, 'id');
        $current = (array)$this->resource->snapshot($id);
        $this->resource->write(ResourceChange::merge($current, $args), $current);
        return [];
    }
}
