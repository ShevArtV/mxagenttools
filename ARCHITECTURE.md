# mxAgentTools — архитектура

Инструменты админки MODX Revolution 2.8 для ИИ-агента: агент через них делает то же, что
контент-менеджер в админке. Пакет один на все сайты; знания о сайте (какая TV за что
отвечает, где задаётся порядок подборок, что нельзя трогать) — только в инструкции агента,
не в коде и не в конфиге.

Пакет не знает ни об одном конкретном сайте: в коде, конфиге-примере и документации нет имён сайтов,
их TV, шаблонов и путей. Всё о сайте — в документации сайта, которую читает агент.

Пакет — **провайдер mxApi** (требует mxApi ≥ 1.2.1 для MODX 2): инструменты — эндпоинты API,
их описание (паспорт `describe()` эндпоинта) и есть документация для агента. Агент берёт каталог из
`GET /mxapi/v1/meta/openapi` и вызывает эндпоинты по HTTPS. Как писать провайдер —
`docs/components/mxapi/providers.md` и `endpoint-reference.md` (docs.modx.pro), образец — `apps/mxapi2`.

## API

Префикс — `mxapi.route_prefix` (по умолчанию `/mxapi/v1`), пути ниже — без него. Контекст
эндпоинтов — `mgr`. Scope: `agent.read` (чтение), `agent.write` (запись и откат); права
MODX — `mxapi_agent_read`, `mxapi_agent_write` (политика пакета, резолвер — как в mxApi).

Каждый инструмент — эндпоинт с паспортом: `id` (`agent.resource.update`), `title`,
`description` (что делает, когда применять, что вернёт — это читает агент), `parameters` с
описанием каждого параметра и примером.

- Чтение — `GET`, ответ `data`.
- Запись — `POST` с параметром `mode`: `plan` (по умолчанию) — без записи, ответ `targets`,
  `before`, `after`, `diff` (строки «поле: было → станет»), `hash` = sha1 канонического JSON
  снимков `before`; `apply` + `expect=<hash>` — снимки пересняты, hash ≠ expect → ошибка
  `stale_plan` «изменилось после показа»; иначе журнал до записи, запись, «после» в журнал.
  Ответ `journal` (id записи журнала), `diff`, `created`.
- Журнал: `GET /agent/journal` (последние записи), `GET /agent/journal/{id}`,
  `POST /agent/journal/{id}/rollback` — тот же `mode=plan|apply`: текущее состояние ≠ «после»
  → в плане `diverged: true` и diff «у бота → сейчас → станет»; `apply` с `expect` из плана.
- Файлы: `POST /agent/uploads` (base64 в теле, имя, sha256) → `token`, живёт 1 час; дальше
  token — параметр `gallery.upload` и т.п.
- `note` — у любого POST: кто и откуда попросил (уходит в журнал).
- Отказ по сути (нет объекта, запрещённая привязка `@EVAL`) — ошибка mxApi с кодом `refusal`
  и текстом для человека; сбой — `processor_error`/`internal_error` штатно.

## Хранение

Без файлов на диске сайта и без конфига: журнал и загрузки — таблицы пакета
(`mxagenttools_journal`: id, endpoint, params, before, after, created, user, note, at, rolled_back;
`mxagenttools_upload`: token, name, sha256, data, expires). Копии удалённых файлов галереи —
в записи журнала (base64). Чистка загрузок старше срока — при каждом вызове upload.
Настройки пакета (`mxagenttools.*`): лимит размера загрузки, срок жизни загрузки, глубина журнала.

## Модель

```
Core\Executor        общий цикл plan/apply/rollback, hash, журнал
Core\Journal         записи в таблице mxagenttools_journal
Api\Provider          регистрация эндпоинтов в mxApi (плагин пакета на событие регистрации mxApi)
Api\Endpoint\*        тонкие эндпоинты: паспорт + вызов инструмента через Executor
Core\Processor       runProcessor + разбор ошибок + выбор <class_key>CreateProcessor (msProduct, msCategory, CollectionContainer)
Core\Refusal         отказ для человека (не сбой)
Core\Uploads         загрузки в таблице mxagenttools_upload, token; срок жизни, лимит размера

Entity\EntityInterface   type(); snapshot(id): ?array; restore(array $snapshot): void; remove(id): void
Entity\Resource          поля + все TV шаблона + (msProduct) данные товара, опции, доп. категории
Entity\Tv                поля TV + шаблоны + все значения (для отката удаления)
Entity\Gallery           галерея товара целиком (msProductFile, порядок, файлы — копии в журнал при удалении)
Entity\Modification      msopModification + опции + картинки модификации (если msoptionsprice установлен)
Entity\Setting           системная настройка (modSystemSetting)
Entity\ClientConfig      настройка ClientConfig (если установлен)

Tool\ToolInterface       паспорт (id, title, description, parameters) — из него строится эндпоинт
Tool\ReadToolInterface   read(array $args): array
Tool\WriteToolInterface  targets(array $args): EntityRef[]; preview(args, before): after; apply(args): created EntityRef[]
```

Откат общий: для каждого target — `restore(before)` (нет в `before` — объект был создан
инструментом → `remove`); созданные при записи (`created`) — `remove`. Ресурс удаляется
мягко (корзина MODX), отмена удаления — `resource/undelete`.

## Инструменты (первый набор)

Чтение: `resource.get`, `resource.find`, `resource.children`, `template.list` (шаблоны и их TV),
`tv.get`, `tv.list`, `gallery.get`, `modification.list` (по товару), `setting.get`,
`clientconfig.get`, `context.list`, `query` (произвольная выборка xPDO по классу, where,
select, sort, limit ≤ 200; запрещены классы пользователей, сессий, менеджер-логов и
элементов-кода: modUser*, modSession, modManagerLog, modActiveUser, modTemplate*content,
modChunk/modSnippet/modPlugin — поле `snippet`/`content`/`plugincode` не отдаётся).

Запись: `resource.create`, `resource.update` (поля, TV, данные товара, опции, доп. категории),
`resource.delete`, `resource.undelete`, `tv.create`, `tv.update` (вкл. привязку к шаблонам),
`tv.delete`, `gallery.upload`, `gallery.remove`, `gallery.sort`, `modification.create`,
`modification.update`, `modification.delete`, `modification.images`, `setting.update`,
`clientconfig.update`.

Имена инструментов = `id` эндпоинтов с префиксом `agent.`. Пакеты-расширения (miniShop2,
msOptionsPrice2, Collections, ClientConfig) подгружаются, если установлены; эндпоинт, которому
нет пакета, не регистрируется и в каталог не попадает.

## Подключение на сайте

1. Поставить mxApi и mxAgentTools (mxPackages).
2. Завести пользователя MODX для агента, дать группе политику с `mxapi_agent_*` и правами
   на процессоры ресурсов/TV/ms2 (контекст `mgr`).
3. Во вкладке mxApi пользователя — клиент client_credentials со scope `agent.read agent.write`.
   Секрет клиента — в хранилище секретов агента, не в репо.

Конфига в репо сайта нет. Правила работы с контентом сайта — в документации сайта, не в пакете.
