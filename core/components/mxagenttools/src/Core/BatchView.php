<?php

namespace MxAgentTools\Core;

/**
 * Ответ пакетной правки для человека: сколько объектов, сколько меняется и без изменений, сводка «поле → сколько
 * объектов», «было → станет» первых SHOW объектов и more — сколько изменённых не показано. Больше SHOW объектов —
 * снимки before/after из ответа убираются (они остаются в журнале), hash — по всем снимкам, как у любой записи.
 */
final class BatchView
{
    const SHOW = 20;

    public static function apply(array $out, array $before, array $after): array
    {
        $changed = [];
        $summary = [];
        foreach (array_keys($before + $after) as $key) {
            $b = $before[$key] ?? null;
            $a = $after[$key] ?? null;
            $paths = self::changedPaths($b, $a);
            if (!$paths) {
                continue;
            }
            $changed[] = (string)$key;
            $type = substr((string)$key, 0, (int)strpos((string)$key, ':'));
            foreach ($paths as $path) {
                $name = $type . ' ' . preg_replace('/\[\d+\]/', '[]', $path);
                $summary[$name] = ($summary[$name] ?? 0) + 1;
            }
        }
        $shown = array_slice($changed, 0, self::SHOW);
        $out['total'] = count($before + $after);
        $out['changed'] = count($changed);
        $out['unchanged'] = $out['total'] - count($changed);
        $out['summary'] = $summary;
        $out['diff'] = Diff::lines(array_intersect_key($before, array_flip($shown)), array_intersect_key($after, array_flip($shown)));
        $out['more'] = max(0, count($changed) - count($shown));
        if ($out['total'] > self::SHOW) {
            unset($out['before'], $out['after'], $out['predicted']);
        }
        return $out;
    }

    /** Пути, где снимки различаются (создание/удаление — «*») */
    private static function changedPaths(?array $b, ?array $a): array
    {
        if ($b === null || $a === null) {
            return $b === $a ? [] : ['*'];
        }
        $fb = Diff::flatten($b);
        $fa = Diff::flatten($a);
        $paths = [];
        foreach (array_keys($fb + $fa) as $path) {
            if (($fb[$path] ?? null) !== ($fa[$path] ?? null)) {
                $paths[] = (string)$path;
            }
        }
        return $paths;
    }
}
