# Data sources & schema

> 🇷🇺 Русская версия: [`ru/DATA.md`](ru/DATA.md) (английский — источник правды).

> What each source is, where its numbers come from, and the unified record everything is
> normalized into. **Real vs sample is labeled per source** and never blurred.

## Sources

The assignment names four inputs. We fill each with the most real data we can honestly get:

| Source file | What it represents | How we obtain it | Reality |
|-------------|--------------------|------------------|---------|
| `google_ads_keywords` | Site.pro keywords already used in Ads | Keyword Planner, URL-seeded on `site.pro` | **Real** ideas + metrics (Google's associations for the domain — a proxy for the live account, labeled as such) |
| `search_console_queries` | Real Search Console queries for Site.pro | No access to their Search Console → **sample** (realistic structure); API adapter planned | **Sample** |
| `ahrefs_organic_keywords` | Site.pro's own organic keywords | Keyword Planner on `site.pro` (no Ahrefs subscription) | **Real** metrics via Keyword Planner |
| `ahrefs_paid_keywords` | Competitors' paid keywords | Keyword Planner, URL-seeded on competitor domains | **Real** competitor ideas + metrics |

**What we deliberately do not fake:** Site.pro's private, account-specific data — their live
Ads keyword list, their actual Search Console queries, and their Ahrefs subscription. We
don't have those accounts, so they're represented as clearly-labeled sample files with
realistic structure. The `ApiAdapter` seam (see [`API.md`](API.md)) replaces a sample with a
real feed once Site.pro grants access.

## Provenance of real metrics

Real search **volume**, **CPC**, and **competition** come from **Google Ads Keyword Planner**
(via our Google Ads API access), seeded on a domain URL to harvest the keywords Google
associates with it. Interpretation notes:

- `avg_monthly_searches` — a 12-month average, bucketed/rounded by Google. Treat as relative,
  not exact.
- `competition` — advertiser competition (LOW/MEDIUM/HIGH), **not** SEO difficulty.
- CPC low/high — a commercial-value range; high CPC at low volume can still be worth bidding.

Metrics are fetched at build time and written into the input files; the deployed app only
imports files and holds **no credentials**.

## Input columns per source

Adapters accept the common export shapes; unknown columns are ignored, missing required ones
fail the batch with a clear message.

- **google_ads_keywords** — `keyword`, `avg_monthly_searches`, `competition`, `cpc`,
  `match_type`, `campaign`, `status` (used/paused)
- **search_console_queries** — `query`, `clicks`, `impressions`, `ctr`, `position`
- **ahrefs_organic_keywords** — `keyword`, `volume`, `kd`, `cpc`, `position`, `url`, `traffic`
- **ahrefs_paid_keywords** — `keyword`, `volume`, `cpc`, `competitor_domain`, `url`

Where a source lacks a language/market column, language is inferred by detection.

## The generated dataset (`sample-data/`)

The four inputs are generated and committed under [`sample-data/`](../sample-data/) (with a
JSON variant of **each** source under `sample-data/json/`) — ready to upload in the admin area:

| File | Rows | Real / sample |
|------|-----:|---------------|
| `google_ads_keywords.csv` | 52 | real volume/CPC (already-used set) |
| `search_console_queries.csv` | 79 | sample metrics, real terms |
| `ahrefs_organic_keywords.csv` | 136 | real volume/CPC; KD/pos/traffic sample |
| `ahrefs_paid_keywords.csv` | 112 | real competitor volume/CPC |

Real metrics come from Google Ads Keyword Planner across six languages (en, de, es, fr, pt,
it) and five competitor domains. The set deliberately carries brand terms, duplicates, junk,
low-volume rows, and forbidden examples so every cleaning rule is exercised. Details:
[`sample-data/README.md`](../sample-data/README.md).

## Unified `keyword` schema

Every source maps onto this record; the admin views and export read from it.

| Field | Meaning |
|-------|---------|
| `batch_id` | the import batch it came from (FK → `import_batch`, cascade) |
| `source` | `google_ads` \| `search_console` \| `ahrefs_organic` \| `ahrefs_paid` |
| `raw_term` | the term exactly as imported |
| `normalized_term` | lowercased, trimmed, whitespace-collapsed, token-sorted (dedup key) |
| `language`, `geo` | language / market (from the source, or detected for Search Console) |
| `avg_monthly_searches` | monthly search volume (real where available; null for Search Console) |
| `cpc` | cost-per-click (one value per keyword; null where the source has none) |
| `competition` | advertiser competition — `LOW` \| `MEDIUM` \| `HIGH` (Google Ads only) |
| `competitor_domain` | set for competitor (paid) keywords |
| `source_url` | landing/target URL from the source, when present |
| `clicks`, `impressions`, `position` | extra metrics carried by Search Console / Ahrefs organic |
| `raw_data` | the full original source row, as JSON, for audit |
| `is_junk`, `is_duplicate`, `is_brand`, `below_volume` | cleaning flags (set from stage 4) |
| `is_already_used`, `is_forbidden` | preparation flags (set from stage 5) |
| `stage` | `imported` \| `cleaned` \| `prepared` \| `ad_ready` |
| `drop_reason` | why it was excluded (drives the funnel) |
| `dedup_group_id` | canonical group for merged duplicates |

The `import_batch` table records one row per upload: `source`, `filename`, `format` (csv|json),
`rows_total` / `rows_imported` / `rows_skipped`, `status` (imported|failed), and a `message`.

## Language & target URL

- Keywords are grouped by `language`/`market` for both ad copy and campaign structure. Three
  sources carry an explicit language column; **Search Console has none, so language is
  inferred** by a small marker-word/diacritic detector (defaults to English when nothing is
  distinctive — see `services/LanguageDetector.php`).
- Each language maps to the correct localized Site.pro landing URL. Verified live (2026-07-01):

  | Language | Landing URL |
  |----------|-------------|
  | en | `https://site.pro/` |
  | de | `https://site.pro/de/` |
  | es | `https://site.pro/es/` |
  | fr | `https://site.pro/fr/` |
  | it | `https://site.pro/it/` |
  | pt | `https://site.pro/pt-br/` (no `/pt` page exists; the site serves Portuguese at `/pt-br/`) |

  The map lives in `backend/config/params.php` and can be overridden in the admin area once
  canonical URLs are provided (used from stage 6).
