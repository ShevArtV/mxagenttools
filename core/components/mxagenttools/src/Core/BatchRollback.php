<?php

namespace MxAgentTools\Core;

use Throwable;

/**
 * Откат пакетной операции целиком: все её записи журнала (kind=apply с тем же batch), новые первыми — порции
 * откатываются в обратном порядке записи. Тот же plan → apply: plan собирает планы отката каждой записи, hash —
 * от их hash по порядку; apply пересчитывает планы, сверяет общий hash и откатывает запись за записью. Сбой на
 * середине — остановка: откаченные записи остаются откаченными (у каждой свой журнал отката), ответ — что сделано.
 */
class BatchRollback
{
    /** @var Executor */
    private $executor;
    /** @var JournalInterface */
    private $journal;

    public function __construct(Executor $executor, JournalInterface $journal)
    {
        $this->executor = $executor;
        $this->journal = $journal;
    }

    public function plan(string $batch): array
    {
        $plans = $this->plans($batch);
        $out = ['mode' => 'plan', 'batch' => $batch, 'entries' => [], 'total' => 0, 'changed' => 0, 'diverged' => false,
            'summary' => [], 'diff' => [], 'divergence' => [], 'hash' => self::hash($plans)];
        foreach ($plans as $id => $plan) {
            $view = isset($plan['summary']) ? $plan
                : BatchView::apply($plan, (array)($plan['before'] ?? []), (array)($plan['after'] ?? []));
            $out['entries'][] = ['journal' => $id, 'changed' => !empty($plan['changed']), 'diverged' => !empty($plan['diverged']),
                'objects' => $view['total']];
            $out['total'] += $view['total'];
            $out['changed'] += $view['changed'];
            $out['diverged'] = $out['diverged'] || !empty($plan['diverged']);
            foreach ($view['summary'] as $path => $n) {
                $out['summary'][$path] = ($out['summary'][$path] ?? 0) + $n;
            }
            $out['diff'] = array_merge($out['diff'], (array)$view['diff']);
            $out['divergence'] = array_merge($out['divergence'], (array)($plan['divergence'] ?? []));
        }
        $out['more'] = max(0, count($out['diff']) - 2 * BatchView::SHOW);
        $out['diff'] = array_slice($out['diff'], 0, 2 * BatchView::SHOW);
        $out['divergence'] = array_slice($out['divergence'], 0, 2 * BatchView::SHOW);
        if (!$out['divergence']) {
            unset($out['divergence']);
        }
        return $out;
    }

    public function apply(string $batch, string $expect, string $note): array
    {
        $plans = $this->plans($batch);
        $hash = self::hash($plans);
        if (!hash_equals($hash, $expect)) {
            throw Refusal::stale('объекты операции изменились после показа плана отката — покажите план заново', ['current_hash' => $hash]);
        }
        $done = [];
        foreach ($plans as $id => $plan) {
            try {
                $res = $this->executor->rollbackApply($id, (string)$plan['hash'], $note);
            } catch (Throwable $e) {
                return ['mode' => 'apply', 'batch' => $batch, 'complete' => false, 'done' => $done, 'failed' => $id,
                    'error' => $e->getMessage(), 'left' => count($plans) - count($done)];
            }
            $done[] = ['journal_of' => $id, 'journal' => $res['journal'] ?? null, 'changed' => $res['changed']];
        }
        return ['mode' => 'apply', 'batch' => $batch, 'complete' => true, 'done' => $done];
    }

    /** Планы отката неоткаченных записей операции, новые первыми */
    private function plans(string $batch): array
    {
        $plans = [];
        foreach ($this->journal->batchEntries($batch) as $id) {
            $entry = $this->journal->read($id);
            if (empty($entry['rolled_back'])) {
                $plans[$id] = $this->executor->rollbackPlan($id);
            }
        }
        if (!$plans) {
            throw new Refusal("у операции {$batch} нет записей для отката (нет такой операции или она уже откачена)");
        }
        return $plans;
    }

    private static function hash(array $plans): string
    {
        $parts = [];
        foreach ($plans as $id => $plan) {
            $parts[] = $id . ':' . $plan['hash'];
        }
        return sha1(implode('|', $parts));
    }
}
