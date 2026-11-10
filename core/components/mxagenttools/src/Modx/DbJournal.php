<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\JournalInterface;
use MxAgentTools\Core\Refusal;
use RuntimeException;

/**
 * Журнал в таблице пакета mxagenttools_journal (класс mxatJournal). Глубина — настройка
 * mxagenttools.journal_depth: при создании записи старше последних N удаляются (0 — хранить всё).
 * Это служебная таблица пакета без процессоров — пишется объектами xPDO.
 */
class DbJournal implements JournalInterface
{
    const COLUMNS = ['endpoint', 'kind', 'status', 'params', 'targets', 'before', 'predicted', 'after', 'created', 'kept',
        'note', 'error', 'rolled_back', 'rollback_of'];
    const LIST_COLUMNS = ['id', 'endpoint', 'kind', 'status', 'targets', 'username', 'note', 'error', 'at', 'updatedon',
        'rolled_back', 'rollback_of'];

    /** @var \modX */
    private $modx;
    /** @var int */
    private $depth;

    public function __construct(\modX $modx, int $depth)
    {
        $this->modx = $modx;
        $this->depth = $depth;
    }

    public function create(array $entry): int
    {
        $row = $this->modx->newObject('mxatJournal');
        $row->set('user', (int)$this->modx->user->get('id'));
        $row->set('at', date('Y-m-d H:i:s'));
        $this->fill($row, $entry);
        $id = (int)$row->get('id');
        if ($this->depth > 0 && $id > $this->depth) {
            $this->modx->removeCollection('mxatJournal', ['id:<=' => $id - $this->depth]);
        }
        return $id;
    }

    public function write(int $id, array $entry): void
    {
        $row = $this->modx->getObject('mxatJournal', $id, false);
        if (!$row) {
            throw new RuntimeException("запись журнала {$id} пропала");
        }
        $this->fill($row, $entry);
    }

    public function read(int $id): array
    {
        $row = $id > 0 ? $this->modx->getObject('mxatJournal', $id, false) : null;
        if (!$row) {
            throw new Refusal("записи журнала {$id} нет (или она старше глубины журнала)");
        }
        $out = ['id' => $id];
        foreach (array_merge(self::COLUMNS, ['user', 'username', 'at', 'updatedon']) as $k) {
            $out[$k] = $row->get($k);
        }
        $out['user_id'] = (int)$out['user'];
        $out['user'] = (string)$out['username'];
        return $out;
    }

    public function recent(int $limit, int $offset): array
    {
        $q = $this->modx->newQuery('mxatJournal');
        $q->select($this->modx->getSelectColumns('mxatJournal', 'mxatJournal', '', self::LIST_COLUMNS));
        $q->sortby('id', 'DESC');
        $q->limit($limit, $offset);
        $out = [];
        if ($q->prepare() && $q->stmt->execute()) {
            foreach ($q->stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $row['targets'] = json_decode((string)$row['targets'], true) ?: [];
                $out[] = $row;
            }
        }
        return $out;
    }

    private function fill(\xPDOObject $row, array $entry): void
    {
        foreach (self::COLUMNS as $k) {
            if (array_key_exists($k, $entry)) {
                $row->set($k, $entry[$k]);
            }
        }
        $row->set('username', (string)($entry['user'] ?? $row->get('username')));
        $row->set('updatedon', date('Y-m-d H:i:s'));
        if (!$row->save()) {
            throw new RuntimeException('запись журнала не сохранилась');
        }
    }
}
