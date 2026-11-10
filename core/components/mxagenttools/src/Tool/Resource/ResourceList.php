<?php

namespace MxAgentTools\Tool\Resource;

/** Строки списка ресурсов — общее для resource.find и resource.children */
final class ResourceList
{
    const FIELDS = ['id', 'pagetitle', 'menutitle', 'class_key', 'template', 'parent', 'context_key', 'uri', 'published',
        'hidemenu', 'isfolder', 'deleted', 'menuindex'];

    public static function rows(\modX $modx, \xPDOQuery $q, int $limit, int $offset): array
    {
        $total = $modx->getCount('modResource', clone $q);
        $q->select($modx->getSelectColumns('modResource', 'modResource', '', self::FIELDS));
        $q->limit($limit, $offset);
        $rows = [];
        if ($q->prepare() && $q->stmt->execute()) {
            foreach ($q->stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $rows[] = array_map('strval', $row);
            }
        }
        return ['total' => $total, 'resources' => $rows];
    }

    public static function limit(array $args): array
    {
        $limit = (int)($args['limit'] ?? 30);
        return [max(1, min($limit, 200)), max(0, (int)($args['offset'] ?? 0))];
    }
}
