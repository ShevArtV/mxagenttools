<?php

namespace MxAgentTools\Core;

/**
 * Запрет кода сайта в полях TV: привязки @EVAL (исполняемый PHP), @FILE (чтение файлов сервера),
 * @SELECT (SQL), @CHUNK и @SNIPPET (вызов элементов-кода). @INHERIT, @RESOURCE, @DOCUMENT разрешены.
 * Разбор — как modTemplateVar::getBindingDataFromValue + parseBinding (MODX 2.8): значение после trim
 * начинается с «@», команда ищется регуляркой БЕЗ привязки к началу — «@x @EVAL …» MODX тоже исполнит как EVAL.
 * Поэтому проверяется любое вхождение запрещённой команды в такой строке, не только первое.
 * @SNIPPET в MODX 2.8 привязкой не является — запрещён на случай расширений, разбирающих его.
 */
final class BindingGuard
{
    /** Команды parseBinding (modTemplateVar::$bindings) и SNIPPET */
    const MODX_BINDINGS = ['FILE', 'CHUNK', 'DOCUMENT', 'RESOURCE', 'SELECT', 'EVAL', 'INHERIT', 'DIRECTORY', 'SNIPPET'];
    const DENIED = ['EVAL', 'FILE', 'SELECT', 'CHUNK', 'SNIPPET'];

    /** Отказ, если значение — запрещённая привязка; $field — для сообщения */
    public static function check(string $field, $value): void
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                self::check("{$field}.{$k}", $v);
            }
            return;
        }
        foreach (self::commands((string)$value) as $cmd) {
            if (in_array($cmd, self::DENIED, true)) {
                throw new Refusal("{$field}: привязка @{$cmd} запрещена — это код сайта, его меняют разработчики");
            }
        }
    }

    /** Команды привязок в значении, если MODX сочтёт его привязкой (начинается с «@»); иначе [] */
    public static function commands(string $value): array
    {
        $value = trim($value);
        if ($value === '' || $value[0] !== '@') {
            return [];
        }
        preg_match_all('/@(' . implode('|', self::MODX_BINDINGS) . ')/i', $value, $m);
        return array_values(array_unique(array_map('strtoupper', $m[1])));
    }
}
