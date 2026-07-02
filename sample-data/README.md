# Sample data

Input files for the pipeline (import → clean → prepare → export). Upload them in the admin
area — both **CSV** and **JSON** are accepted.

## Provenance — real vs sample

Real search metrics (monthly volume, CPC, competition) come from **Google Ads Keyword
Planner**, seeded on `site.pro` (six languages) and competitor domains. The files contain
only keywords and numbers — no credentials. Metrics that are specific to a private account
we don't have access to are **sample** values with realistic structure, noted below.

| File | Represents | Rows | Metrics |
|------|-----------|-----:|---------|
| `google_ads_keywords.csv` | Site.pro keywords already running in Ads (the "already-used" set) | 52 | real volume/CPC; clicks/impr/cost derived |
| `search_console_queries.csv` | Search Console queries | 79 | **sample** (no GSC access) — real terms, plausible clicks/impr/position |
| `ahrefs_organic_keywords.csv` | Site.pro organic keywords | 136 | real volume/CPC; KD/position/traffic sample |
| `ahrefs_paid_keywords.csv` | Competitors' paid keywords (Wix, Squarespace, Weebly, GoDaddy, Tilda) | 112 | real volume/CPC + competitor domain |

**JSON equivalents of all four sources** live in `json/` — same rows, same fields, native JSON
types — so the JSON importer can be tested for every source, not just some:
`json/google_ads_keywords.json`, `json/search_console_queries.json`,
`json/ahrefs_organic_keywords.json`, `json/ahrefs_paid_keywords.json`. Pick the matching **Source**
in the upload form (the format is detected from the `.csv` / `.json` extension).

## What the data intentionally contains

So the cleaning pipeline has real work to do, the set includes:

- **Six languages** — en, de, es, fr, pt, it — for "group by language".
- **Brand terms** — own (`site.pro`, `sitepro …`) and competitor (`wix …`, `squarespace …`)
  → brand removal.
- **Duplicates & near-duplicates** — e.g. `website builder` / `web builder` / `website
  builder site` across several files → dedup / merge.
- **Junk** — empty, single-char, symbols-only, digits-only, and an overlong query → junk removal.
- **Low-volume** rows (< 50/mo) → volume filter.
- **Forbidden** examples (`casino …`, `adult …`) → forbidden-list removal.
- **Already-used vs new** — everything in `google_ads_keywords` counts as already used;
  competitor paid keywords absent from our own sources are opportunities.

Field meanings and the unified target schema are in [`../docs/DATA.md`](../docs/DATA.md).
