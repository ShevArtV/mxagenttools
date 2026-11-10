<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\Refusal;

/** TV: поиск, TV шаблона и сырые значения — общее для ресурсов и TV */
class TemplateVars
{
    /** @var \modX */
    private $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    /** @param int|string $idOrName */
    public function find($idOrName): ?\modTemplateVar
    {
        $where = is_int($idOrName) || ctype_digit((string)$idOrName) ? ['id' => (int)$idOrName] : ['name' => (string)$idOrName];
        return $this->modx->getObject('modTemplateVar', $where, false) ?: null;
    }

    /** @param int|string $idOrName */
    public function get($idOrName): \modTemplateVar
    {
        $tv = $this->find($idOrName);
        if (!$tv) {
            throw new Refusal("TV «{$idOrName}» нет на сайте");
        }
        return $tv;
    }

    /** @return \modTemplateVar[] TV шаблона по порядку (rank привязки) */
    public function ofTemplate(int $template): array
    {
        if ($template <= 0) {
            return [];
        }
        $q = $this->modx->newQuery('modTemplateVar');
        $q->innerJoin('modTemplateVarTemplate', 'TVT', 'TVT.tmplvarid = modTemplateVar.id');
        $q->where(['TVT.templateid' => $template]);
        $q->sortby('TVT.rank', 'ASC');
        $q->sortby('modTemplateVar.id', 'ASC');
        $out = [];
        foreach ($this->modx->getIterator('modTemplateVar', $q, false) as $tv) {
            $out[] = $tv;
        }
        return $out;
    }

    /**
     * Сырое значение TV, как его хранит админка. Не getTVValue (отдаёт renderOutput: разделители, привязки)
     * и не getValue (читает кэш объектов — после записи было бы старое значение): строка modTemplateVarResource
     * без кэша, а нет строки — default_text (resource/update удаляет строку, если значение равно умолчанию).
     */
    public function raw(\modTemplateVar $tv, int $resource): string
    {
        $row = $this->modx->getObject('modTemplateVarResource', ['tmplvarid' => $tv->get('id'), 'contentid' => $resource], false);
        return (string)($row ? $row->get('value') : $tv->get('default_text'));
    }

    /** name => сырое значение всех TV шаблона */
    public function values(int $template, int $resource): array
    {
        $out = [];
        foreach ($this->ofTemplate($template) as $tv) {
            $out[(string)$tv->get('name')] = $this->raw($tv, $resource);
        }
        return $out;
    }

    /** name => id всех TV шаблона */
    public function ids(int $template): array
    {
        $ids = [];
        foreach ($this->ofTemplate($template) as $tv) {
            $ids[(string)$tv->get('name')] = (int)$tv->get('id');
        }
        return $ids;
    }

    /** name => id для TV шаблона; TV не из шаблона — отказ (процессор молча не сохранил бы значение) */
    public function idsFor(int $template, array $names): array
    {
        $ids = $this->ids($template);
        $out = [];
        foreach ($names as $name) {
            if (!isset($ids[$name])) {
                throw new Refusal("TV «{$name}» не привязан к шаблону {$template} — значение не сохранится");
            }
            $out[$name] = $ids[$name];
        }
        return $out;
    }
}
