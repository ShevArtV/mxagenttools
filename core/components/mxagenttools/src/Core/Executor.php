<?php

namespace MxAgentTools\Core;

use MxAgentTools\Entity\FileKeeperInterface;
use MxAgentTools\Entity\SoftDeleteInterface;
use MxAgentTools\Tool\ReadToolInterface;
use MxAgentTools\Tool\WriteToolInterface;
use Throwable;

/**
 * Общий цикл для всех инструментов: read; plan → apply (expect = hash «было») → журнал; откат записи журнала
 * тем же plan → apply. Инструменты цикл не повторяют: они называют задетые объекты, предсказывают «станет» и пишут.
 * Журнал пишется ДО записи: упадёт процессор посередине — откат всё равно знает «было».
 */
class Executor
{
    /** @var Entities */
    private $entities;
    /** @var JournalInterface */
    private $journal;
    /** @var string */
    private $user;

    public function __construct(Entities $entities, JournalInterface $journal, string $user)
    {
        $this->entities = $entities;
        $this->journal = $journal;
        $this->user = $user;
    }

    public function read(ReadToolInterface $tool, array $args): array
    {
        return $tool->read($args);
    }

    /** Без записи: targets, before, after (предсказанное), diff, hash = sha1 канонического JSON before */
    public function plan(WriteToolInterface $tool, array $args): array
    {
        $refs = $tool->targets($args);
        $before = $this->entities->snapshots($refs);
        $after = $tool->preview($args, $before);
        return ['mode' => 'plan', 'targets' => self::keys($refs), 'before' => $before, 'after' => $after,
            'diff' => Diff::lines($before, $after), 'hash' => Canon::hash($before)];
    }

    public function apply(WriteToolInterface $tool, array $args, string $expect, string $note): array
    {
        $refs = $tool->targets($args);
        $before = $this->entities->snapshots($refs);
        $hash = Canon::hash($before);
        if (!hash_equals($hash, $expect)) {
            throw Refusal::stale('объекты изменились после показа «было → станет» — покажите план заново', ['current_hash' => $hash]);
        }
        $predicted = $tool->preview($args, $before);
        $entry = ['kind' => 'apply', 'endpoint' => $tool->name(), 'params' => $args, 'note' => $note, 'user' => $this->user,
            'status' => 'started', 'targets' => self::keys($refs), 'before' => $before, 'predicted' => $predicted,
            'kept' => $this->keep($refs, $before, $predicted)];
        $id = $this->journal->create($entry);
        try {
            $created = $tool->apply($args);
        } catch (Throwable $e) {
            $this->fail($id, $entry, $e);
            throw $e;
        }
        $after = $this->entities->snapshots(array_merge($refs, $created));
        $entry = ['status' => 'done', 'created' => self::keys($created), 'after' => $after] + $entry;
        $this->journal->write($id, $entry);
        return ['mode' => 'apply', 'journal' => $id, 'targets' => $entry['targets'], 'created' => $entry['created'],
            'diff' => Diff::lines($before, $after), 'unexpected' => $this->unexpected($predicted, $after)];
    }

    /**
     * План отката: before — как сейчас, after — как было до записи. Если после записи объекты меняли
     * (сейчас ≠ «после» из журнала) — diverged и diff «у бота → сейчас → станет»; apply с expect=hash этого плана
     * откатывает поверх чужих правок осознанно.
     */
    public function rollbackPlan(int $id): array
    {
        $s = $this->state($id);
        $out = ['mode' => 'plan', 'journal' => $id, 'endpoint' => $s['entry']['endpoint'] ?? '',
            'targets' => self::keys($s['refs']), 'before' => $s['current'], 'after' => $s['target'],
            'diff' => Diff::lines($s['current'], $s['target']), 'hash' => $s['hash'], 'changed' => $s['changed'],
            'diverged' => $s['diverged']];
        if (!$s['changed']) {
            $out['note'] = 'уже как до записи';
        }
        if ($s['diverged']) {
            $out['divergence'] = Diff::threeWay((array)($s['entry']['after'] ?? []), $s['current'], $s['target']);
            $out['note'] = isset($s['entry']['after'])
                ? 'после записи бота объекты меняли — откат вернёт «было» поверх этих изменений'
                : 'запись оборвалась на середине — состояние «после» неизвестно, сверьте diff';
        }
        if (!empty($s['entry']['rolled_back'])) {
            $out['rolled_back'] = (int)$s['entry']['rolled_back'];
        }
        return $out;
    }

    public function rollbackApply(int $id, string $expect, string $note): array
    {
        $s = $this->state($id);
        if (!hash_equals($s['hash'], $expect)) {
            throw Refusal::stale('объекты изменились после показа плана отката — покажите план заново', ['current_hash' => $s['hash']]);
        }
        if (!$s['changed']) {
            return ['mode' => 'apply', 'journal_of' => $id, 'changed' => false, 'note' => 'уже как до записи'];
        }
        $entry = $s['entry'];
        $undo = ['kind' => 'rollback', 'endpoint' => $entry['endpoint'] ?? '', 'rollback_of' => $id, 'params' => [],
            'note' => $note, 'user' => $this->user, 'status' => 'started', 'targets' => self::keys($s['refs']),
            'created' => [], 'before' => $s['current'], 'predicted' => $s['target'],
            'kept' => $this->keep($s['refs'], $s['current'], $s['target'])];
        $undoId = $this->journal->create($undo);
        try {
            $this->restore($s['refs'], $s['current'], $s['target'], $s['created'], (array)($entry['kept'] ?? []));
        } catch (Throwable $e) {
            $this->fail($undoId, $undo, $e);
            throw $e;
        }
        $after = $this->entities->snapshots($s['refs']);
        $this->journal->write($undoId, ['status' => 'done', 'after' => $after] + $undo);
        $this->journal->write($id, ['rolled_back' => $undoId] + $entry);
        return ['mode' => 'apply', 'journal' => $undoId, 'journal_of' => $id, 'changed' => true, 'diverged' => $s['diverged'],
            'diff' => Diff::lines($s['current'], $after), 'unexpected' => $this->unexpected($s['target'], $after)];
    }

    /** Состояние для отката: ссылки, цель (before записи; созданное — null), текущее, его hash, расхождение */
    private function state(int $id): array
    {
        $entry = $this->journal->read($id);
        if (!isset($entry['targets'], $entry['before']) || !is_array($entry['targets'])) {
            throw new Refusal("запись журнала {$id} — не запись инструмента");
        }
        $created = (array)($entry['created'] ?? []);
        $refs = array_map([EntityRef::class, 'fromKey'], array_merge($entry['targets'], $created));
        $target = [];
        foreach ($entry['targets'] as $key) {
            $target[$key] = $entry['before'][$key] ?? null;
        }
        foreach ($created as $key) {
            $target[$key] = null;
        }
        $current = $this->entities->snapshots($refs);
        return ['entry' => $entry, 'refs' => $refs, 'created' => $created, 'target' => $target, 'current' => $current,
            'hash' => Canon::hash($current), 'changed' => !Canon::equal($this->effective($current), $target),
            'diverged' => !isset($entry['after']) || !Canon::equal($current, $entry['after'])];
    }

    /** Созданные — remove (с конца), остальные — restore(before) или remove, если до записи их не было */
    private function restore(array $refs, array $current, array $target, array $createdKeys, array $kept): void
    {
        $effective = $this->effective($current);
        foreach (array_reverse($refs) as $ref) {
            if (in_array($ref->key(), $createdKeys, true) && $effective[$ref->key()] !== null) {
                $this->entities->get($ref->type())->remove($ref->id());
            }
        }
        foreach ($refs as $ref) {
            $key = $ref->key();
            if (in_array($key, $createdKeys, true)) {
                continue;
            }
            $entity = $this->entities->get($ref->type());
            if ($target[$key] === null) {
                if ($effective[$key] !== null) {
                    $entity->remove($ref->id());
                }
            } elseif (!Canon::equal($current[$key], $target[$key])) {
                $entity->restore($target[$key] + (isset($kept[$key]) ? [FileKeeperInterface::KEPT => $kept[$key]] : []));
            }
        }
    }

    /** Копии файлов, которые запись удалит (до записи — в запись журнала) */
    private function keep(array $refs, array $before, array $after): array
    {
        $kept = [];
        foreach ($refs as $ref) {
            $entity = $this->entities->get($ref->type());
            $key = $ref->key();
            if ($entity instanceof FileKeeperInterface && $before[$key] !== null) {
                $files = $entity->keep($before[$key], $after[$key] ?? null);
                if ($files) {
                    $kept[$key] = $files;
                }
            }
        }
        return $kept;
    }

    /** Снимок «в корзине» для сравнения с «объекта нет» */
    private function effective(array $snapshots): array
    {
        foreach ($snapshots as $key => $snapshot) {
            $entity = $this->entities->get(EntityRef::fromKey($key)->type());
            if ($snapshot !== null && $entity instanceof SoftDeleteInterface && $entity->isRemoved($snapshot)) {
                $snapshots[$key] = null;
            }
        }
        return $snapshots;
    }

    /** Где записалось не так, как предсказал план (плагины, авто-псевдоним, права) */
    private function unexpected(array $predicted, array $after): array
    {
        return Diff::unexpected($predicted, array_intersect_key($after, $predicted));
    }

    private function fail(int $id, array $entry, Throwable $e): void
    {
        $entry['status'] = 'failed';
        $entry['error'] = $e->getMessage();
        try {
            $this->journal->write($id, $entry);
        } catch (Throwable $ignored) {
            // исходная ошибка важнее; запись «started» в журнале уже есть
        }
    }

    /** @param EntityRef[] $refs */
    private static function keys(array $refs): array
    {
        return array_values(array_map(function (EntityRef $ref) {
            return $ref->key();
        }, $refs));
    }
}
