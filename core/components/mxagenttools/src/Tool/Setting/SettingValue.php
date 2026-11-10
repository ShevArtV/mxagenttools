<?php

namespace MxAgentTools\Tool\Setting;

use MxAgentTools\Core\Refusal;

/**
 * Значение настройки из args: mxApi считает пустую строку «параметр не передан», поэтому очистка значения —
 * отдельным флагом clear, а не value="".
 */
final class SettingValue
{
    public static function from(array $args): string
    {
        if (!empty($args['clear'])) {
            if (isset($args['value']) && (string)$args['value'] !== '') {
                throw new Refusal('value и clear вместе не задаются');
            }
            return '';
        }
        if (!isset($args['value']) || !is_scalar($args['value'])) {
            throw new Refusal('нужен value (пустое значение — clear: true)');
        }
        return (string)$args['value'];
    }
}
