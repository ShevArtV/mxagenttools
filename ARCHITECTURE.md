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
  Процессору нужен путь — файл на время вызова кладётся в `core/components/mxagenttools/tmp/<token>/` сайта
  (не в системный `/tmp`: на общем сервере его каталог чужой) и удаляется в `finally`.
- `note` — у любого POST: кто и откуда попросил (уходит в журнал).
- Отказ по сути (нет объекта, запрещённая привязка `@EVAL`) — ошибка mxApi с кодом `refusal`
  и текстом для человека; сбой — `processor_error`/`internal_error` штатно.

Секреты: значение настройки (системной, контекста, ClientConfig), ключ которой похож на секрет (`pass`, `secret`,
`token`, `key`, `private`, `salt`, `hash`), наружу — `***` (`Core\Secrets`: чтение, query, снимки и diff в ответах
записи, отката и журнала); hash плана — по настоящим значениям. Запись таких настроек разрешена.

### Пакетные правки (1.1.0)

Любой объём — порциями по `mxagenttools.batch_limit` (500) объектов:
1. `GET /agent/resources/select` (`resource.select`) или `/agent/products/select` (`product.select`, раздел вместе с доп.
   категориями) — фильтр фиксируется на сервере выборкой: id пишутся в `mxagenttools_selection` одним `INSERT … SELECT`
   (без загрузки в PHP и без потолка), ответ — `selection`, `total`, `pages`, `page_size`, первые 20 id. Живёт
   `mxagenttools.selection_ttl` (24 ч), старые чистятся при создании новой. Смена шаблона/родителя между порциями
   выборку не сдвигает.
2. Пакетные записи (`resource.batch` — template/parent/fields/tvs, `product.categories` — add/remove доп. категорий,
   `product.prices` — percent/add/set, round 0/1/10/100 nearest/down/up, mode price/discount/undiscount, вместе с
   модификациями msOptionsPrice2) принимают `selection` + `page` или явный `ids` (≤ лимита) и `batch` — id операции
   клиента. Тот же plan → apply(expect); план — `total`, `changed`, `unchanged`, `summary` (поле → сколько объектов),
   `diff` первых 20 объектов и `more`, hash — по всем снимкам (`Core\BatchView`). Запись — по одному объекту
   процессором; упадёт посередине — журнал уже содержит «было» всей порции.
3. Журнал: `batch` у записи, `GET /agent/journal?batch=…`; `POST /agent/journal/batch/{batch}/rollback` (plan/apply) —
   откат всех порций операции, новые первыми (`Core\BatchRollback`); сбой посередине — `complete: false`, повтор
   продолжит с оставшихся.

### Чтение кода (1.1.0)

`element.list` / `element.get` — чанки, шаблоны, сниппеты, плагины: поля, категория, код (у статичного — файл, если
внутри сайта), события плагина, TV шаблона. `file.list` / `file.read` — каталог `pdotools_elements_path` (подстановки
`{core_path}` и т.п.): realpath, выход за каталог и симлинки наружу — отказ, размер — до `mxagenttools.file_max_kb`
(`Core\SafePath`, `Modx\CodeFiles`). `query` читает modChunk/modSnippet/modPlugin/modTemplate с полями кода. Записи
кода нет.

### Сжатие картинок при приёме (1.1.0)

`POST /agent/uploads` ужимает картинку (JPEG, PNG, WebP, GIF без анимации): поворот по EXIF, вписывание в
`max_width` × `max_height`, `quality`, `format` keep | jpeg | webp, `strip_metadata`. Умолчания —
`mxagenttools.image_max_side` (2000), `image_quality` (82), `image_format` (keep), `image_strip_metadata` (1);
значения для сайта агент берёт из инструкции сайта. Движок — Imagick, иначе GD (`Image\*`, по модулю на движок);
SVG, не-картинки, анимация и уже малое — как есть. Ответ — `image {engine, before, after, processed, reason}`.

### Внешние коды модификаций (1.1.0-beta5)

`modification.export` — строки {product, modification, name, external_id, source} по списку товаров (≤ 200, не-товары —
в `missing`). Код по умолчанию `mod-<rid>-<mid>` (`source: temp`). Перед ответом — событие `mxAgentToolsOnExternalIds`
(`Modx\ExternalIds`; заводит `resolver.events.php`) с `modifications` [mid => rid] и пустым `\ArrayObject` `codes`:
плагин сайта кладёт `$codes[$mid] = '<код>'`, непустая строка заменяет код (`source: 1c`). Откуда коды — знает сайт.

## Хранение

Без файлов на диске сайта и без конфига: журнал и загрузки — таблицы пакета
(`mxagenttools_journal`: id, endpoint, params, before, after, created, user, note, at, rolled_back;
`mxagenttools_upload`: token, name, sha256, data, expires; `mxagenttools_selection`: selection, object_id, createdon;
у журнала — `batch`). Копии удалённых файлов галереи —
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
`tv.get`, `tv.list`, `gallery.get`, `modification.list` (по товару), `modification.export` (модификации до 200 товаров с внешним кодом), `setting.get`,
`clientconfig.get`, `context.list`, `query` (произвольная выборка xPDO по классу, where,
select, sort, limit ≤ 200; запрещены классы пользователей, сессий, менеджер-логов: modUser*, msOrder*, msCustomer*,
mxApi*, mxat*, modSession, modManagerLog, modActiveUser; код элементов читается),
`element.list`, `element.get`, `file.list`, `file.read`, `resource.select`, `product.select`.

Запись: `resource.create`, `resource.update` (поля, TV, данные товара, опции, доп. категории),
`resource.delete`, `resource.undelete`, `tv.create`, `tv.update` (вкл. привязку к шаблонам),
`tv.delete`, `gallery.upload`, `gallery.remove`, `gallery.sort`, `modification.create`,
`modification.update`, `modification.delete`, `modification.images`, `setting.update`,
`clientconfig.update`; пакетные — `resource.batch`, `product.categories`, `product.prices`.

Имена инструментов = `id` эндпоинтов с префиксом `agent.`. Пакеты-расширения (miniShop2,
msOptionsPrice2, Collections, ClientConfig) подгружаются, если установлены; эндпоинт, которому
нет пакета, не регистрируется и в каталог не попадает.

## Подключение на сайте

1. Поставить mxApi и mxAgentTools (mxPackages).
2. Завести пользователя MODX для агента, дать группе политику с `mxapi_agent_*` и правами
   на процессоры ресурсов/TV/ms2 (контекст `mgr`).
3. Во вкладке mxApi пользователя — клиент client_credentials со scope `agent.read agent.write meta.read`.
   Секрет клиента — в хранилище секретов агента, не в репо.

Конфига в репо сайта нет. Правила работы с контентом сайта — в документации сайта, не в пакете.
