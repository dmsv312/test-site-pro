# Import / export contracts

> 🇷🇺 Русская версия: [`ru/API.md`](ru/API.md) (английский — источник правды).

> Status: **import → cleaning → preparation → ad generation → export are all implemented**, with
> **two** Google Ads export formats (Editor desktop CSV + web-UI bulk-upload ZIP, decision 34).
> The assignment says "later we will use API", so import is built contract-first behind adapters.
> See `docs/PLAN.md` for how it fits the architecture, and `docs/DATA.md` for field meanings.

## Import

Every source (CSV, JSON, and — later — an external API) is normalized into the same
`keyword` records through a source **adapter**. Adding a source means adding an adapter,
not touching the pipeline. All routes below are in the login-gated admin area.

### Upload (CSV / JSON) — implemented

```
POST /import/upload   (login-gated, CSRF-protected, multipart/form-data)
  UploadForm[source]   one of: google_ads | search_console | ahrefs_organic | ahrefs_paid
  UploadForm[file]     the CSV or JSON export (≤ 20 MB; format from the file extension)
→ 302 → /import/keywords?KeywordSearch[batch_id]=<id>   on success
      → /import/index with an error flash                on failure
```

Each upload creates an `import_batch` row (`rows_total` / `rows_imported` / `rows_skipped` /
`status` / `message`). Unknown columns are ignored; a missing required column fails the batch
with a clear message. Required column per source: `keyword` (`query` for Search Console).

### Admin routes

```
GET  /import/index      import form + per-source summary + import history
GET  /import/keywords   the full keyword table (filter by source/language/stage/min volume, sort, paginate)
POST /import/clear      wipe all imported data (for re-importing during a demo)
```

### Admin routes — pipeline (login-gated)

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

Ad generation (`/ads/run`) writes one responsive search ad per ad group: it prefers stored,
offline-authored copy (a committed JSON keyed by `language:theme_key`) and falls back to a
deterministic per-language template engine, so the deployed host needs no AI credentials. Every ad
is re-validated against the RSA limits before it is stored, and the target URL is taken from the ad
group (never the copy). Like preparation, ad generation is fully derived and rebuilt each run;
re-running preparation rebuilds the ad groups and cascades the ads away.

Cleaning is the head of the pipeline: `POST /cleaning/run` recomputes from the imported data and
**resets everything downstream — stage 5 (preparation) and stage 6 (ads)**, because rebuilding the
ad groups cascades their generated ads away. So after re-cleaning, run `/prepare/run` **then**
`/ads/run` to rebuild both. Re-running preparation alone likewise clears the ads, so re-run
`/ads/run` after it too.

### Console (same services, no web layer)

```
yii import/samples [dir]          import all four sample-data files (default: /opt/sample-data)
yii import/file <source> <path>   import one CSV/JSON file
yii clean/run                     run the cleaning pipeline (resets stages 5–6; then run prepare + adgen)
yii prepare/run                   run preparation: drops → merge → group by language + theme (resets stage 6)
yii adgen/run                     generate one RSA per ad group (stored copy preferred, template fallback)
yii export/file [path]            write the Google Ads Editor (desktop) CSV (default: @runtime/export/…)
yii export/bulk [path]            write the Google Ads web-UI bulk-upload ZIP (default: @runtime/export/…)
```

### External API (future)

`ApiSourceReader` is the wired-in seam: when Site.pro grants access to Search Console / Google
Ads / Ahrefs, a live reader replaces a sample file there and the adapters + pipeline stay
untouched. Not implemented yet.

The same adapter interface will back a fetcher for Google Search Console, the Google Ads
account, and Ahrefs once credentials are provided by Site.pro. Until then those sources are
imported as clearly-labeled sample files. Accepted input columns per source and the normalized
target fields are documented in `docs/DATA.md`.

## Export — implemented

Google Ads has **two** import paths that take **different file formats**, so the app offers both
(decision 34). Both are **derived on demand** from the current `ad_group` / `generated_ad` /
`keyword` state (decision 31), so they always reflect the latest preparation and generation, and both
sanitize keyword text at the boundary and write only ads flagged `is_valid`. RFC-4180 formatting
(comma-separated, `"`-quoted with doubled inner quotes, CRLF), UTF-8 without a BOM, lives in one
shared `CsvWriter`.

```
GET /export/index         HTML preview of the campaigns + both download options
GET /export/download       Google Ads Editor (desktop) CSV     (google-ads-editor-import-<date>.csv)
GET /export/download-bulk  Google Ads web-UI bulk-upload ZIP   (google-ads-bulk-upload-<date>.zip)
```

### A. Google Ads Editor (desktop) — one combined CSV (decision 29)

A single sheet recreates the whole tree on import (Account → Import → From file). Every row names its
`Campaign` (+ `Campaign Type` = Search) and `Ad Group`; the row's *type* is read from which columns
it fills:

| Row type | Filled columns |
|----------|----------------|
| Keyword | `Keyword` · `Match Type` (**Phrase**, decision 30) · `Final URL` |
| Responsive search ad | `Headline 1..15` (≤30 chars) · `Description 1..4` (≤90 chars) · `Path 1` · `Path 2` · `Final URL` |

Editor recognizes the ad as an RSA from the headline/description columns — its CSV schema has **no
ad-type column**, so none is emitted. `Final URL` is the ad group's verified localized target URL —
never taken from any generated text. `Max CPC` is left blank. New campaigns import as **stubs that
still need a budget and bid strategy** in Editor before they can be posted.

### B. Google Ads web UI — bulk-upload ZIP (decision 34)

The web tool (Tools → Bulk actions → Uploads) has **no combined format** — every official template is
single-entity — so the ZIP holds **one CSV per entity**, uploaded in dependency order (a bundled
`README.txt` states it): `campaigns.csv` → `ad-groups.csv` → `keywords.csv` →
`responsive-search-ads.csv`. Column headers are verbatim from Google's official bulk-upload templates,
with details that differ from Editor: an `Action` = `Add` column, per-entity status columns
(`Campaign status` / `Status` / `Ad status`), match type spelled **`Phrase match`**, an explicit
`Ad type` = `Responsive search ad`, and a first description column named just **`Description`** (then
`Description 2..4`). Campaigns import **paused, on `Manual CPC`, with no budget column**, so an
accidental import never spends — set a budget and enable before serving.

## Normalized keyword record

See `docs/DATA.md` → "Unified `keyword` schema" for the canonical field list shared by
import, the admin views, and export.
