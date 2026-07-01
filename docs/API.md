# Import / export contracts

> Status: **planned / partially implemented.** This is the target contract; the assignment
> says "later we will use API", so import is designed contract-first behind adapters. See
> `docs/PLAN.md` for how it fits the architecture, and `docs/DATA.md` for field meanings.

## Import

Every source (CSV, JSON, and — later — an external API) is normalized into the same
`keyword` records through a source **adapter**. Adding a source means adding an adapter,
not touching the pipeline.

### Upload (CSV / JSON) — planned

```
POST /import
  multipart/form-data:
    file        the CSV or JSON export
    source      one of: google_ads | search_console | ahrefs_organic | ahrefs_paid
    format      csv | json   (auto-detected from the file when omitted)
→ 200: { batch_id, source, rows_total, rows_imported, rows_skipped }
```

Accepted input columns per source and the normalized target fields are documented in
`docs/DATA.md`. Unknown columns are ignored; missing required columns fail the batch with
a clear error.

### External API (future)

The same adapter interface will back a fetcher for Google Search Console, the Google Ads
account, and Ahrefs once credentials are provided by Site.pro. Until then those sources are
imported as clearly-labeled sample files.

## Export

```
GET /export/google-ads         → Google Ads Editor CSV (campaigns / ad groups / keywords + RSA ads)
GET /export/preview            → HTML preview of the campaigns grouped by language
```

The export is a **Google Ads Editor**-compatible CSV: ad-group keywords with match type and
final URL, plus responsive search ads (up to 15 headlines ≤30 chars, 4 descriptions ≤90
chars) per language group, each pointing at the correct localized target URL.

## Normalized keyword record

See `docs/DATA.md` → "Unified `keyword` schema" for the canonical field list shared by
import, the admin views, and export.
