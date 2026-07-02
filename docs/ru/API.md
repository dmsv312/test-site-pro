# Import / export contracts

> RU: перевод английского оригинала (API.md). При расхождении английская версия — источник правды.

> Статус: **импорт → очистка → подготовка → генерация объявлений → экспорт — всё реализовано**, с
> **двумя** форматами экспорта в Google Ads (десктопный Editor CSV + ZIP для массовой загрузки через веб-интерфейс, решение 34).
> В задании сказано «позже будем использовать API», поэтому импорт построен «сначала контракт», за адаптерами.
> См. `docs/PLAN.md` — как это укладывается в архитектуру, и `docs/DATA.md` — значения полей.

## Import

Каждый источник (CSV, JSON и — позже — внешний API) нормализуется в одни и те же
записи `keyword` через **адаптер** источника. Добавить источник — значит добавить адаптер,
а не трогать конвейер. Все маршруты ниже находятся в закрытой логином административной зоне.

### Upload (CSV / JSON) — реализовано

```
POST /import/upload   (login-gated, CSRF-protected, multipart/form-data)
  UploadForm[source]   one of: google_ads | search_console | ahrefs_organic | ahrefs_paid
  UploadForm[file]     the CSV or JSON export (≤ 20 MB; format from the file extension)
→ 302 → /import/keywords?KeywordSearch[batch_id]=<id>   on success
      → /import/index with an error flash                on failure
```

Каждая загрузка создаёт строку `import_batch` (`rows_total` / `rows_imported` / `rows_skipped` /
`status` / `message`). Неизвестные столбцы игнорируются; отсутствие обязательного столбца приводит к сбою пакета
с понятным сообщением. Обязательный столбец для каждого источника: `keyword` (`query` для Search Console).

### Административные маршруты

```
GET  /import/index      import form + per-source summary + import history
GET  /import/keywords   the full keyword table (filter by source/language/stage/min volume, sort, paginate)
POST /import/clear      wipe all imported data (for re-importing during a demo)
```

### Административные маршруты — конвейер (login-gated)

```
GET  /cleaning/index    cleaning funnel (junk → dedup → brand → volume) + drop reasons
POST /cleaning/run      run cleaning; resets the downstream (see below)
GET  /prepare/index     preparation funnel + campaign preview (languages → ad groups)
POST /prepare/run       drop already-used/forbidden → keep canonicals → group by language + theme
GET  /ads/index         generated-ads preview (per language → ad groups → RSA copy + char counts)
POST /ads/run           (re)generate one responsive search ad per ad group
GET  /export/index         export preview (campaigns → ad groups, counts, both artifacts)
GET  /export/download      download the Google Ads Editor (desktop) CSV (keywords + RSA ads)
GET  /export/download-bulk download the Google Ads web-UI bulk-upload ZIP (one CSV per entity)
GET  /rules/index          editable thresholds + brand / forbidden term lists
```

Генерация объявлений (`/ads/run`) создаёт одно адаптивное поисковое объявление на каждую группу объявлений: она предпочитает сохранённые,
подготовленные офлайн тексты (закоммиченный JSON с ключами вида `language:theme_key`) и в качестве запасного варианта использует
детерминированный шаблонизатор с текстами по языкам, поэтому развёрнутому хосту не нужны учётные данные для AI. Каждое объявление
повторно проверяется на соответствие ограничениям RSA перед сохранением, а целевой URL берётся из группы
объявлений (никогда из текста). Как и подготовка, генерация объявлений полностью выводится из данных и пересобирается при каждом запуске;
повторный запуск подготовки пересобирает группы объявлений и каскадно удаляет их объявления.

Очистка — это голова конвейера: `POST /cleaning/run` пересчитывает данные из импортированных и
**сбрасывает всё, что ниже по потоку, — этап 5 (подготовка) и этап 6 (объявления)**, потому что пересборка
групп объявлений каскадно удаляет сгенерированные для них объявления. Поэтому после повторной очистки запустите `/prepare/run`, **затем**
`/ads/run`, чтобы пересобрать оба. Повторный запуск одной только подготовки точно так же очищает объявления, поэтому запустите
`/ads/run` и после неё.

### Консоль (те же сервисы, без веб-слоя)

```
yii import/samples [dir]          import all four sample-data files (default: /opt/sample-data)
yii import/file <source> <path>   import one CSV/JSON file
yii clean/run                     run the cleaning pipeline (resets stages 5–6; then run prepare + adgen)
yii prepare/run                   run preparation: drops → merge → group by language + theme (resets stage 6)
yii adgen/run                     generate one RSA per ad group (stored copy preferred, template fallback)
yii export/file [path]            write the Google Ads Editor (desktop) CSV (default: @runtime/export/…)
yii export/bulk [path]            write the Google Ads web-UI bulk-upload ZIP (default: @runtime/export/…)
```

### Внешний API (в будущем)

`ApiSourceReader` — это заранее встроенный шов: когда Site.pro предоставит доступ к Search Console / Google
Ads / Ahrefs, живой ридер заменит там файл-образец, а адаптеры и конвейер останутся
нетронутыми. Пока не реализовано.

Тот же интерфейс адаптера будет обслуживать загрузчик для Google Search Console, аккаунта Google
Ads и Ahrefs, как только Site.pro предоставит учётные данные. До тех пор эти источники
импортируются как чётко помеченные файлы-образцы. Принимаемые входные столбцы для каждого источника и нормализованные
целевые поля описаны в `docs/DATA.md`.

## Export — реализовано

У Google Ads есть **два** пути импорта, которые принимают **разные форматы файлов**, поэтому приложение предлагает оба
(решение 34). Оба **вычисляются по запросу** из текущего состояния `ad_group` / `generated_ad` /
`keyword` (решение 31), поэтому они всегда отражают последнюю подготовку и генерацию, и оба
очищают текст ключевых слов на границе и записывают только объявления с флагом `is_valid`. Форматирование по RFC-4180
(с запятыми-разделителями, в кавычках `"` с удвоением внутренних кавычек, CRLF), UTF-8 без BOM, живёт в одном
общем `CsvWriter`.

```
GET /export/index         HTML preview of the campaigns + both download options
GET /export/download       Google Ads Editor (desktop) CSV     (google-ads-editor-import-<date>.csv)
GET /export/download-bulk  Google Ads web-UI bulk-upload ZIP   (google-ads-bulk-upload-<date>.zip)
```

### A. Google Ads Editor (десктоп) — один объединённый CSV (решение 29)

Один лист воссоздаёт всё дерево при импорте (Account → Import → From file). Каждая строка называет свою
`Campaign` (+ `Campaign Type` = Search) и `Ad Group`; *тип* строки считывается из того, какие столбцы
она заполняет:

| Row type | Filled columns |
|----------|----------------|
| Keyword | `Keyword` · `Match Type` (**Phrase**, решение 30) · `Final URL` |
| Responsive search ad | `Headline 1..15` (≤30 chars) · `Description 1..4` (≤90 chars) · `Path 1` · `Path 2` · `Final URL` |

Editor распознаёт объявление как RSA по столбцам заголовков/описаний — в его схеме CSV **нет
столбца типа объявления**, поэтому он не выводится. `Final URL` — это проверенный локализованный целевой URL группы объявлений —
он никогда не берётся из сгенерированного текста. `Max CPC` оставлен пустым. Новые кампании импортируются как **заготовки, которым
всё ещё нужны бюджет и стратегия ставок** в Editor, прежде чем их можно будет опубликовать.

### B. Google Ads веб-интерфейс — ZIP для массовой загрузки (решение 34)

У веб-инструмента (Tools → Bulk actions → Uploads) **нет объединённого формата** — каждый официальный шаблон
однасущностный, — поэтому ZIP содержит **один CSV на сущность**, загружаемых в порядке зависимостей (об этом сказано во вложенном
`README.txt`): `campaigns.csv` → `ad-groups.csv` → `keywords.csv` →
`responsive-search-ads.csv`. Заголовки столбцов дословно взяты из официальных шаблонов массовой загрузки Google,
с деталями, отличными от Editor: столбец `Action` = `Add`, столбцы статуса для каждой сущности
(`Campaign status` / `Status` / `Ad status`), тип соответствия, записанный как **`Phrase match`**, явный
`Ad type` = `Responsive search ad` и первый столбец описания, названный просто **`Description`** (затем
`Description 2..4`). Кампании импортируются **приостановленными, на `Manual CPC`, без столбца бюджета**, поэтому
случайный импорт никогда не тратит деньги — задайте бюджет и включите перед показом.

## Нормализованная запись keyword

См. `docs/DATA.md` → «Unified `keyword` schema» — канонический список полей, общий для
импорта, административных представлений и экспорта.