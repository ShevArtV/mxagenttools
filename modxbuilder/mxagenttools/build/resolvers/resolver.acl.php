<?php
/**
 * Права эндпоинтов mxAgentTools и политика пакета.
 *
 * Права живут в namespace `mxapi` (их проверяет ядро mxApi), поэтому добавляются в шаблон `mxapiTemplate`,
 * созданный mxApi. Незаведённое право mxApi считает отсутствующим (fail-closed) — без резолвера любой
 * non-sudo получил бы insufficient_permission.
 *
 * Политика `mxagenttoolsAgent` (на mxapiTemplate): load + mxapi_agent_read + mxapi_agent_write + права ядра mxApi,
 * без которых клиент агента не работает: mxapi_auth_token (выпуск токена), mxapi_auth_revoke, mxapi_meta_read
 * (каталог /meta/openapi — по нему агент узнаёт инструменты). `load` обязателен:
 * проверка прав идёт через modNamespace, и MODX сам требует load при загрузке объекта. В mxapiDefault права
 * агента НЕ добавляются: иначе каждый, кому выдан API, получил бы запись контента. Политику выдаёт администратор:
 * Access Controls → группа пользователя агента → Namespace Access → mxapi → mxagenttoolsAgent. Группам пакет
 * ничего не назначает.
 *
 * Поле data политики — только через get('data') + is_array(): xPDO отдаёт его массивом (phptype json), а
 * json_decode поверх массива вернул бы null и обнулил набор прав.
 *
 * @var xPDOTransport $transport
 * @var array $options
 */
if ($transport->xpdo) {
    /** @var modX $modx */
    $modx =& $transport->xpdo;

    $permissions = [
        'mxapi_agent_read' => 'mxAgentTools: чтение контента инструментами агента',
        'mxapi_agent_write' => 'mxAgentTools: запись контента и откат инструментами агента',
    ];

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            /** @var modAccessPolicyTemplate $template */
            $template = $modx->getObject('modAccessPolicyTemplate', ['name' => 'mxapiTemplate']);
            if (!$template) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[mxagenttools] Шаблон политики mxapiTemplate не найден — сначала установите mxApi.');

                return true;
            }
            $templateId = (int)$template->get('id');

            foreach ($permissions as $name => $description) {
                if ($modx->getObject('modAccessPermission', ['template' => $templateId, 'name' => $name])) {
                    continue;
                }
                /** @var modAccessPermission $permission */
                $permission = $modx->newObject('modAccessPermission');
                $permission->fromArray(['template' => $templateId, 'name' => $name, 'description' => $description,
                    'value' => 1], '', true, true);
                $permission->save();
                $modx->log(modX::LOG_LEVEL_INFO, '[mxagenttools] Добавлено право: ' . $name);
            }

            // права ядра mxApi (заводит сам mxApi в mxapiTemplate) — клиенту агента нужны токен и каталог
            $data = ['load' => true, 'mxapi_auth_token' => true, 'mxapi_auth_revoke' => true, 'mxapi_meta_read' => true];
            foreach (array_keys($permissions) as $name) {
                $data[$name] = true;
            }
            /** @var modAccessPolicy $policy */
            $policy = $modx->getObject('modAccessPolicy', ['name' => 'mxagenttoolsAgent']);
            if (!$policy) {
                $policy = $modx->newObject('modAccessPolicy');
                $policy->fromArray(['name' => 'mxagenttoolsAgent', 'description' => 'Инструменты агента mxAgentTools: чтение и запись',
                    'parent' => 0, 'template' => $templateId, 'class' => '', 'lexicon' => 'permissions'], '', true, true);
                $policy->set('data', $data);
                $policy->save();
                $modx->log(modX::LOG_LEVEL_INFO, '[mxagenttools] Создана политика mxagenttoolsAgent.');
            } else {
                // на upgrade выключенное администратором не включаем — только добавляем новые права
                $current = $policy->get('data');
                $current = is_array($current) ? $current : [];
                $changed = false;
                foreach ($data as $name => $value) {
                    if (!array_key_exists($name, $current)) {
                        $current[$name] = $value;
                        $changed = true;
                    }
                }
                if ($changed) {
                    $policy->set('data', $current);
                    $policy->save();
                }
            }
            $modx->getCacheManager()->refresh(['system_settings' => []]);
            break;

        case xPDOTransport::ACTION_UNINSTALL:
            // права и политику не удаляем: на них висят выданные доступы групп
            break;
    }
}

return true;
