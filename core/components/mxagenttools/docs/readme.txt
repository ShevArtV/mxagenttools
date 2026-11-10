mxAgentTools — инструменты админки MODX Revolution 2.8 для ИИ-агента, провайдер mxApi.

Агент делает через API то же, что контент-менеджер в админке: ресурсы, TV, галерея miniShop2,
модификации msOptionsPrice2, системные настройки, ClientConfig. Пакет не знает правил сайта:
что и когда менять — в документации сайта, которую читает агент. Запрещён только код сайта:
инструментов для шаблонов, чанков, сниппетов и плагинов нет, в TV нельзя @EVAL, @FILE, @SELECT,
@CHUNK, @SNIPPET.

Каталог эндпоинтов с описанием каждого параметра — GET /mxapi/v1/meta/openapi (provider mxagenttools).

Запись — в два шага: POST с mode=plan (по умолчанию) возвращает diff «было → станет» и hash,
POST с mode=apply и expect=hash пишет процессорами MODX и отвечает id записи журнала.
Откат: POST /agent/journal/{id}/rollback с тем же plan/apply.

Подключение на сайте:
1. Установить mxApi (>= 1.2.1) и mxAgentTools. Сбросить кэш (иначе плагин регистрации не подхватится).
2. Завести пользователя MODX для агента. Его группе выдать:
   - Namespace Access: namespace mxapi, политика mxagenttoolsAgent (load, mxapi_agent_read, mxapi_agent_write);
   - Context Access: контекст mgr, политика с правами процессоров, которые агенту нужны
     (save_document, delete_document, undelete_document, save_tv, settings, msproduct_save, msproductfile_save…).
3. Во вкладке mxApi пользователя — клиент client_credentials со scope "agent.read agent.write".
   Секрет — в хранилище секретов агента.

Настройки: mxagenttools.upload_max_mb, mxagenttools.upload_ttl (секунды), mxagenttools.journal_depth.
Таблицы: mxagenttools_journal, mxagenttools_upload.

Сборка transport: modxbuilder/mxagenttools/build/build.package.php (каталог modxbuilder/tools — из modxBuilder).
Тесты без MODX: composer install && php vendor/bin/phpunit.
