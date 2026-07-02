# Work log

> Single source of truth for status. Newest entries on top. Every build stage lands here.

## Current status

- **Done:** stage 9 — **deploy hardening + end-to-end smoke over the public URL**. A 15-check
  live smoke passes against https://sitepro.dm312sv.online (guest gate → CSRF login → all seven
  admin pages 200 → `text/csv` export download → error page leaks no stack trace). Hardening:
  the session / CSRF / remember-me cookies are now `Secure; HttpOnly; SameSite=Lax` on the
  HTTPS host; `COOKIE_VALIDATION_KEY` is **required in production** (no shared key in git);
  nginx hides its version and the PHP `X-Powered-By`, and adds `X-Content-Type-Options`,
  `X-Frame-Options: DENY`, `Referrer-Policy`. PHPStan / PHPCS / 83 unit tests still green;
  pipeline data intact (107 keywords / 19 ads / 6 campaigns / 19 ad groups). **The project is
  now stage 9/9 — all planned stages done.**
- **Next:** none planned. Optional refinements only (hand-authored ad copy for the five
  non-English languages; per-ad-group URL overrides; a smarter theme clusterer).
- **Live:** https://sitepro.dm312sv.online · local http://127.0.0.1:8100 (admin login from `.env`)

- **Prev:** stage 7 — campaign preview + **Google Ads Editor CSV export**. A single combined,
  Editor-compatible CSV (decision 29) recreates the whole tree: one campaign per language, its themed
  ad groups, every prepared keyword (match type **Phrase**, decision 30), and one responsive search
  ad per ad group — each pointing at its verified localized target URL. The export is **derived on
  demand** from the current pipeline state (decision 31 — no persisted artifact table), so it always
  reflects the latest preparation/generation. Keyword text is sanitized at the boundary; only
  **valid** ads are written; formatting is RFC-4180 (quoted, CRLF), UTF-8 without a BOM. Admin
  **preview** at `/export` with a download button + `yii export/file [path]`; 10 unit tests. Verified:
  **126 rows** (107 keywords + 19 ads) across 6 campaigns / 19 ad groups; `/export` renders (200) and
  `/export/download` streams `text/csv` as an attachment.

## Stages

| # | Stage | Status |
|---|-------|--------|
| 1 | Data-access spike (validate real keyword metrics) | ✅ done |
| 2 | Skeleton: Yii2 + PostgreSQL + Docker, admin login | ✅ done |
| 3 | Import (CSV/JSON) + `keyword` model + admin GridView | ✅ done |
| 4 | Cleaning pipeline + funnel dashboard | ✅ done |
| 5 | Prepare for Google Ads (already-used/forbidden/merge/group by language+theme) | ✅ done |
| 6 | Ad generation (per language, correct URL) — stored/template + RSA validation | ✅ done |
| 7 | Campaign preview + Google Ads Editor CSV export | ✅ done |
| 8 | Real data collection → input files + labeled samples | ✅ done (early) |
| 9 | Deploy hardening + smoke | ✅ done |

## Journal

### 2026-07-02 — Stage 9: deploy hardening + end-to-end smoke over the public URL
- **Live smoke (15 checks, all pass)** against https://sitepro.dm312sv.online, driving the real
  Cloudflare-tunnel path (edge → nginx → php-fpm), not just localhost: a guest hitting `/` is
  redirected to login (302); the login page issues a CSRF token; a form POST with the `.env`
  credentials authenticates (302 + session cookie); all seven admin pages render authenticated
  (`/import/index`, `/import/keywords`, `/cleaning`, `/prepare`, `/ads`, `/export`, `/rules` → 200);
  `/export/download` streams `text/csv` as an attachment (127-line CSV); and `/site/error` shows a
  generic page with no stack trace (debug off). Re-run green after every change.
- **Auth cookies hardened.** The session (`PHPSESSID`), CSRF (`_csrf`) and remember-me (`_identity`)
  cookies are now `Secure; HttpOnly; SameSite=Lax`. `Secure` is keyed off an `https://` `APP_URL`
  (decision 33), so the public HTTPS host marks them Secure while local `http://127.0.0.1` dev keeps
  login working. Verified over the wire on all three cookies.
- **No shared cookie key in git.** `COOKIE_VALIDATION_KEY` is read from the environment and is now
  **mandatory in production** — the app throws on boot if it's missing in prod (decision 33). The
  previous committed fallback key was removed; local/dev falls back to an obviously-labeled
  placeholder.
- **nginx hardening (config-only, no image rebuild).** `server_tokens off` (response is `Server: nginx`,
  no version), `fastcgi_hide_header X-Powered-By` (the PHP version no longer leaks), and baseline
  security headers on every response — `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
  `Referrer-Policy: strict-origin-when-cross-origin`. Verified at the origin (bypassing Cloudflare)
  and through the tunnel.
- **Ops note:** `nginx.conf` is a **single-file bind mount**, which binds an inode — an atomic-rename
  edit on the host leaves the container on the old inode, so `docker compose restart web` won't pick
  up the change. Recreate the container (`docker compose up -d --force-recreate web`) to re-resolve
  the mount. The `./backend` **directory** mount doesn't have this problem (PHP edits are live).
- **Verified nothing drifted:** PHPStan (0 errors), PHPCS clean, full unit suite **83 pass** after the
  config edits; pipeline data intact (107 prepared keywords / 19 ads / 6 campaigns / 19 ad groups).

### 2026-07-01 — Verified the export against the real Google Ads Editor import format; dropped `Ad Type`
- **Checked the actual file against Google's documented Editor CSV format** (research + adversarial
  verification over real `GoogleAdsEditorExport::render()` output). Confirmed the parts that matter:
  the **combined single file with mixed keyword+ad rows is exactly Google's documented design** (Editor
  reads a row's type from which columns it fills), no BOM (the top cause of "header not recognized" —
  avoided), CRLF, correct RFC-4180 quoting, valid UTF-8 (em-dash preserved), all headers recognized
  (name-matched, order-independent), `Match Type` = `Phrase` valid, and the 3-headline/2-description
  RSA minimum met.
- **One real correction:** the `Ad Type` = "Responsive search ad" column is **not** in Editor's CSV
  schema — Editor infers the RSA from the `Headline`/`Description` columns, so that column would just
  import unmapped. Removed it from the header and ad row, dropped the `AD_TYPE_RSA` constant, and
  switched the internal ad-row discriminator (snapshot + download) from `isset($row['Ad Type'])` to
  `isset($row['Headline 1'])` — the same signal Editor uses. Updated the class docblock, PLAN
  decision 29, API docs and tests to match, and corrected two over-claims (the file doesn't "rebuild
  the whole tree": new campaigns import as **stubs needing a budget + bid strategy**; and the
  encoding is "verified compatible", not a published Google spec).
- **Verified nothing drifted:** re-generated the CSV — still 127 lines (107 keyword + 19 ad rows), now
  with **no `Ad Type` column**; full unit suite green, PHPStan/PHPCS clean.

### 2026-07-01 — Static analysis + lint made runnable and green over the whole codebase
- `composer static` (PHPStan) and `composer cs` (PHPCS) had both silently broken when the portal
  scaffold was removed — each config still listed the deleted `mail/` directory, which aborts the run
  before it analyses anything. On top of that, PHPStan's `paths` never included the `services/` layer,
  so the entire pipeline (import → cleaning → preparation → adgen → export) went unchecked.
- **PHPStan:** dropped the dangling `mail` path, added `services` to `paths` (level 5, whole app now
  covered), and fixed the 6 latent issues it surfaced — all low-risk, none a live bug: a missing
  `@property ad_group_id` on `Keyword`, a widening `@var` and a redundant `is_string`/`isset` guard in
  the import path, a redundant `is_string` in `RsaValidator`, cross-language **duplicate stopword keys**
  in `ThemeClusterer` (the set was unchanged — just written twice), and a **provably-dead** `$best ===
  null` tie-break branch in the clusterer (unreachable because `$bestFreq` starts at −1 and every token
  frequency is ≥1). **No errors.**
- **PHPCS:** dropped the same `mail` path, added `services`/`widgets`, and excluded the legacy
  `PrivateNoUnderscore` rule (this codebase uses modern no-underscore private members and promoted
  constructor properties throughout — the rule contradicted the code's own consistent style).
  `phpcbf` auto-fixed 3 formatting nits (a double-quoted string, two control-structure spacings).
  **Clean.**
- **Verified nothing drifted:** re-ran the full pipeline — 154 cleaned → 107 prepared → 19 ad groups
  → 19 ads (0 invalid) → 126 export rows, identical to before (the clusterer edits are behaviour-
  preserving). Full unit suite **83 pass**.

### 2026-07-01 — Stage 7: campaign preview + Google Ads Editor CSV export
- **One combined Google Ads Editor CSV** (`services/export/`): the pure, side-effect-free
  `GoogleAdsEditorExport` owns the format (column schema, row factories, RFC-4180 rendering) and is
  fully unit-tested; `ExportService` walks the `ad_group` / `generated_ad` / `keyword` state, and
  `ExportController` (`/export`) + `ExportController` console (`yii export/file`) are thin. The file
  carries both entity types in one sheet, disambiguated by which columns a row fills — a **keyword**
  row (`Keyword` + `Match Type`) and a **responsive search ad** row (`Ad Type` = "Responsive search
  ad" + `Headline 1..15` / `Description 1..4` / `Path 1/2`). Every row names its `Campaign`
  (+ `Campaign Type` = Search) and `Ad Group`, so Editor rebuilds the tree on import (decision 29).
- **Phrase match type** on every keyword (decision 30); `Max CPC` left blank (the advertiser sets
  bids). `Final URL` is the ad group's verified localized URL on both the keyword and the ad row —
  **never taken from any generated text**.
- **Derived on demand, not persisted** (decision 31): no `export_file` table; the CSV is a pure
  function of the current state, matching the "fully derived, rebuilt each run" principle of stages
  5–6. Re-preparing/re-generating changes the export automatically.
- **Safe at the boundary.** Keyword text is sanitized before it hits the file (valid UTF-8, control
  characters dropped, whitespace collapsed, lowercased, word order kept — unlike the token-sorted
  `normalized_term`), and per-group duplicate terms are collapsed. Only ads flagged `is_valid` are
  written; an ad group missing a valid ad still exports its keywords and is surfaced as a warning in
  the preview. Output is RFC-4180 (comma-separated, `"`-quoted with doubled inner quotes, CRLF),
  UTF-8 without a BOM — the encoding Editor imports.
- **Verified by hand:** `yii export/file` writes a **127-line** CSV (header + 107 keyword rows + 19
  ad rows) across 6 campaigns / 19 ad groups, match type Phrase, localized Final URLs, UTF-8
  preserved (e.g. `Site.pro — DE`). Authenticated web check: `/export` renders (200) with the right
  counts (6 / 19 / 107 / 19) and `/export/download` returns `text/csv` as an attachment
  (`google-ads-editor-<date>.csv`). **10 new unit tests** (header schema, Phrase default + keyword
  sanitizing incl. control chars, RSA-ceiling spreading, RFC-4180 quoting of commas/quotes, CRLF) —
  full unit suite **83 pass**. PHPCS clean on the new files.

### 2026-07-01 — Stage 6: ad generation (stored/template + RSA validation)
- **One responsive search ad per ad group** (`services/adgen/`, migration `generated_ad` +
  `AdGroup.generatedAd`). For each group the service picks copy, applies the group's authoritative
  target URL, validates, and stores it. Admin preview at `/ads`, console parity `yii adgen/run`.
- **Two sources, template-first correctness.** `TemplateAdGenerator` is a deterministic engine with
  per-language building blocks (en/de/es/fr/it/pt) that weaves the ad group's own theme token into a
  couple of headlines and fills the rest from localized website-builder value props — always in the
  group's language, always valid, no external call. `StoredAdSource` loads higher-quality copy
  authored offline (a committed JSON keyed by `language:theme_key`) and is **preferred when present
  and valid** (decision 3); anything absent or failing validation falls back to the template. So the
  deployed host runs no generation and holds no AI credentials.
- **All copy is untrusted input.** `RsaValidator` enforces Google's RSA limits before anything is
  stored: 3–15 headlines ≤30 chars, 2–4 descriptions ≤90 chars, all distinct, valid UTF-8 with no
  control characters. The **target URL is taken from the ad group, never the generated copy**, so a
  bad string can't redirect a campaign. Optional display paths are length- and content-checked.
- **Generation is the tail of the pipeline.** `generated_ad` is fully derived (rebuilt each run,
  idempotent) and FK-CASCADEs on `ad_group`, so re-running preparation rebuilds the groups and drops
  their ads — **re-prep invalidates stage 6 by design**, exactly as re-cleaning invalidates stage 5
  (decision 20). This resolved the stage-5/6 coupling the plan had left open: `GroupingService`
  dropped its `ad_ready`-group preservation for a plain full rebuild (decision **27 supersedes 26**),
  which keeps the stage-5 funnel math untouched and avoids `theme_key` collisions.
- **Verified by hand:** migration applies; 19 ad groups → **19 ads** (6 from stored EN copy, 13
  template), **0 invalid**, covering all **107** prepared keywords; console and web agree. Re-running
  preparation keeps 107/19 **and** cascades the ads to 0; re-generating restores 19 (three cycles
  stable). The `/ads` page renders authenticated (200, no errors) with per-headline/description
  character counts and the stored/template split. **22 new unit tests** (RsaValidator,
  TemplateAdGenerator, StoredAdSource) — full unit suite **72 pass** at build time.
- **Adversarial code review** (5 lenses — cross-stage/idempotency, RSA/untrusted-input/XSS, template
  generator, UI numbers, docs; each finding verified by refutation, most reproduced empirically in
  the container). Three lenses came back clean; two confirmed and fixed: (1) `TemplateAdGenerator`'s
  cap step didn't strip control characters, so a theme carrying a stray control byte (from an unclean
  source keyword) could produce a headline that failed the RSA validator — it now rejects unclean
  items, restoring the "output always clears RsaValidator" invariant (regression test added → **23
  stage-6 tests, full suite 73 pass**); the ad already failed safely (stored `is_valid=false`, no
  crash/XSS). (2) The `clean/run` guidance said it only "resets stage 5" and prescribed re-running
  only preparation, but the stage-6 CASCADE also clears the ads — corrected the cleaning/preparation
  console + web messages and the API/CLAUDE docs to chain re-clean → re-prepare → re-generate.

### 2026-07-01 — Stage 5.1: pipeline-view grid + funnel consistency (UX pass)
- **Reviewed the admin UI end-to-end** after stage 5 and closed the inconsistencies it exposed:
- **Keyword grid is now organized by pipeline view.** Replaced the stage-4 Kept/Dropped/All toggle
  with four stage-aware tabs — **All · Cleaned · Prepared · Dropped** — each carrying its count in a
  badge, plus a context line stating which view is active and how many rows it holds. Landing on
  "prepared" now reads as a first-class view instead of an "All" grid with a buried column filter.
  The per-column stage filter (and its unreachable `ad_ready` option) was removed — stage is chosen
  via the tabs. Default view is Cleaned once cleaning has run, else All.
- **Cross-page numbers reconcile.** The tab counts are the pipeline counts (All 378 · Cleaned 154 ·
  Prepared 107 · Dropped 271), so the grid agrees with the Cleaning funnel (154 kept) and the
  Prepare funnel (107 prepared). The cleaning page's "View cleaned keywords" link now lands on
  Cleaned (154), matching its own "Kept 154".
- **Cleaning funnel no longer leaks stage-5 drops.** Its drop-reason breakdown was counting every
  `drop_reason`, so preparation's "already used in Google Ads" (47) showed up under cleaning and the
  list disagreed with the funnel's "Dropped 224". Scoped it to rows carrying a cleaning flag, so it
  counts only cleaning's 224 drops.
- **Ad groups link to their keywords.** Each ad group on `/prepare` links to the grid filtered to
  that group (`view=prepared&ad_group_id=N`), with a badge + "clear" on the grid.
- Verified live (assertions + screenshots): tab counts, each view's row count, the ad-group drill-in
  (6 rows), and the cleaning breakdown (0 stage-5 reasons). See PLAN decisions 24–25.

### 2026-07-01 — Stage 5: preparation (drops → merge) + campaign grouping + drift fix
- **Preparation pipeline** (`services/preparation/`): single-purpose rules like cleaning —
  `AlreadyUsedRule` (a term is already-used when its normalized form appears in the `google_ads`
  source, i.e. the account's live keyword list; exact match, so preparation yields a **net-new**
  set) and `ForbiddenRule` (word-boundary match, mirroring `BrandRule`; the list is admin-editable
  and ships empty). `PreparationService` runs already-used → forbidden as a flag-not-delete funnel
  over the cleaned rows, advances survivors to `prepared`, and reports the merge: dedup already
  collapsed each duplicate group to its highest-volume canonical, so the survivor carries the
  group's true (max) volume — volume is **not** summed. Idempotent; console parity `yii prepare/run`.
- **Campaign grouping** (`GroupingService` + `ThemeClusterer`, migration `ad_group` +
  `keyword.ad_group_id`): one campaign per language (target URL from the verified `languageUrlMap`),
  and inside it, themed ad groups. The clusterer is deliberately simple and deterministic — it
  counts meaningful tokens across a language's keywords (multilingual stopwords + bare numbers
  ignored) and names each ad group after the highest-frequency token a keyword contains
  (ties → alphabetical); single-keyword themes fold into a `General` bucket. The `ad_group` table
  is fully derived and rebuilt each run.
- **Admin UI** (`/prepare`, login-gated): the preparation funnel (cleaned → after already-used →
  prepared, with the merged-groups count and a drop breakdown) plus a **campaign preview** —
  per-language cards showing the target URL and each ad group's theme, size, and sample terms.
  Nav entry added between Cleaning and Rules.
- **Verified by hand:** 154 cleaned → 47 already-used dropped → **107 prepared**, grouped into
  **19 ad groups over 6 languages** (en 55, fr 13, pt 12, it 11, de 10, es 6); SQL invariants —
  0 prepared terms present in the google_ads set (net-new holds), every prepared row has an ad
  group, no group's stored count disagrees with its links, `(language, theme_key)` unique. Themes
  are language-appropriate (DE *Erstellen*, FR *Créer/Site*, PT *Criar/Gratuito*, IT *Gratis*).
  Console + web run agree; screenshots taken.
- **Drift bug found & fixed (stage 4 ↔ 5).** Re-running cleaning after preparation drifted
  (154 → 237 → … kept, duplicate keywords reappearing in the prepared set): cleaning's reset/scope
  excluded the rows preparation had locked, but dedup is global — hiding a canonical resurrected
  its duplicates. Fix: **cleaning is the head of the pipeline and a pure function of the imported
  data** — a run resets the whole downstream (all rows → `imported`, all cleaning + preparation
  flags cleared, `ad_group` emptied) then recomputes, so repeated clean→prepare cycles are stable
  (verified: 3 cycles identical, 0 duplicate terms in the prepared set). Re-cleaning now invalidates
  stage 5 by design; the console/UI say to re-run preparation. Also redefined the keyword grid's
  **Kept/Dropped** status to be pipeline-wide (`drop_reason IS NULL AND stage <> imported`) so it
  stays correct as stage 5 advances rows.
- **Adversarial code review** (independent pass, findings verified by refutation) → one latent
  finding fixed: `GroupingService`'s rebuild unlinked *every* keyword, which would clobber an
  `ad_ready` keyword's ad group once stage 6 exists; scoped the unlink to `prepared` and the group
  deletion to non-`ad_ready` groups, so a re-run can't wipe a stage-6 campaign (verified by
  advancing a whole ad group to `ad_ready` and re-running: its links survive, nothing throws). No
  live correctness bugs; other candidates (FK ordering, clusterer edge cases, count math) refuted.

### 2026-07-01 — Stage 4: cleaning pipeline + funnel + editable rules
- **Editable rules** (in the DB, admin-managed — no deploy to tune): `rule_config` thresholds
  (`min_volume` 50, `max_term_length` 80), `brand_term` (site.pro/sitepro + wix/squarespace/
  weebly/godaddy/tilda) and `forbidden_term` (empty; consumed by stage 5). Admin page with a
  thresholds form and add/remove for each list; `max_term_length` is floored at 1 so a bad value
  can't drop the whole dataset.
- **Cleaning pipeline** (`services/cleaning/`): single-purpose rules — `JunkRule` (empty /
  single-char / digits-only / symbols-only / over-length / stopword-only, plus a narrow,
  multilingual-safe gibberish check that flags a term with a vowel-less 5+ letter token like
  `zxcvbnm`), `BrandRule` (word-boundary match so "wix" hits "wix.com" but not "wixel"/Spanish
  "tildar"), `VolumeRule` (keeps rows whose source gave no volume). `CleaningService` runs them
  as a sequential funnel junk → dedup → brand → volume: rules never delete, they set a flag +
  `drop_reason`; counts stay disjoint. Dedup groups by normalized term across the whole dataset,
  keeps the highest-volume row (ties → lowest id), and links the group only when its canonical
  survives. Idempotent (resets state first) and scoped to rows cleaning owns, so a re-run can't
  regress a later stage. Console parity: `yii clean/run`.
- **Funnel dashboard** (`/cleaning`): imported → after junk → after dedup → after brand → cleaned,
  with a drop-reason breakdown that links into the keyword grid (now filterable by `drop_reason`).
- **Keyword grid UX:** a Kept / Dropped / All toggle (defaults to **Kept**, the clean ad-candidate
  set, so dropped junk no longer clutters the default view), and a proper Bootstrap pager instead
  of the cramped default. Import and funnel drill-in links open the relevant view explicitly.
- **Verified by hand:** 378 → 154 kept (6 junk incl. the planted keyboard-mash row, 189 duplicate,
  21 brand, 8 below volume); a second run is identical; no row carries more than one flag; no
  brand term leaks into the kept set. Unit tests for all three rules (38 tests pass).
- **Adversarial code review** (4 lenses, each finding verified by refutation) → 7 confirmed, all
  fixed and re-verified: dedup no longer leaves a duplicate pointing at a dropped canonical; the
  reset is scoped to cleaning-owned rows (won't clobber a future stage-5 `stage`/`drop_reason`);
  brand matching moved from substring to word-boundary (kills false positives like Spanish
  "tildar"); the funnel uses one consistent count basis; `max_term_length` is floored at 1; and
  the rules admin now HTML-encodes term names in flash messages.

### 2026-07-01 — Portal cleanup + login-only access
- Removed the stock Yii scaffold (home/about/contact pages, the contact form + mailer, and Yii
  branding in the header, footer, and login page) so the site shows only what the assignment
  needs. Visuals otherwise unchanged.
- The whole app is now behind login: `defaultRoute` → `import/index`, and `SiteController` keeps
  only `login` / `logout` / `error`. A guest opening any page (including `/`) is redirected to
  the login screen. Verified: guest `/`, `/import/index`, `/import/keywords` → 302 to login;
  removed `/site/about` and `/site/contact` → 404.
- Trimmed the stock tests that covered the removed pages/mailer; kept the auth unit tests
  (`UserTest`, `LoginFormTest`) and the Alert widget test.

### 2026-07-01 — Stage 3: import + keyword model + admin GridView
- **Schema:** two migrations — `import_batch` (one row per upload: source, filename, format,
  row counts, status, message) and the central `keyword` table (raw + normalized term,
  language/geo, volume/CPC/competition, competitor domain, source URL, clicks/impressions/
  position, `raw_data` JSON, cleaning/prep flag columns for later stages, `stage`,
  `drop_reason`, `dedup_group_id`).
- **Import service** (`services/import/`): per-source adapters (Google Ads, Search Console,
  Ahrefs organic, Ahrefs paid) map raw rows onto the unified record behind a common
  interface; CSV/JSON readers plus a documented `ApiSourceReader` seam for "later, API".
  Unknown columns are ignored; a missing required column fails the batch with a clear message.
  Everything runs in one transaction; a failure is still recorded as a `failed` batch.
- **Language:** three sources carry a language column and are trusted; Search Console has none,
  so a small marker-word/diacritic `LanguageDetector` fills it (spot-checked correct on the
  German/Spanish/etc. queries). `normalized_term` is lowercased, whitespace-collapsed, and
  token-sorted (the dedup key for stage 5).
- **Admin area** (login-gated): upload form + per-source summary + import history, and a full
  keyword GridView with filters (source, language, stage, min volume), sorting, and pagination.
- **Auth → env:** admin username/password now come from `.env` (`ADMIN_USERNAME` /
  `ADMIN_PASSWORD`); no credentials hard-coded in PHP. `docker-compose.yml` reads all config
  from `.env` (see `.env.example`); the bcrypt hash is computed only on the login path.
- **Console parity:** `yii import/samples` and `yii import/file <source> <path>` run the same
  service (proves it's decoupled from the web layer).
- **Verified by hand:** migrations apply on container start; all four sample files import
  (52 + 78 + 136 + 112 = 378 keywords; one blank-query Search Console row skipped by design);
  guest → admin pages redirect to login; login with the `.env` credentials works; a JSON
  upload through the web form imports; the GridView renders, filters, and sorts (screenshots).
- **Post-build hardening** (from an adversarial code review of the import path, each fix
  verified against crafted inputs): robust numeric parsing — decimals, scientific notation,
  thousands separators handled; ambiguous values (`12,50`, `1.2K`) rejected as null instead of
  silently mis-scaled; count columns widened to `bigint` so one over-range value no longer
  aborts the file; non-UTF-8 (Windows-1252) CSV cells converted so the row imports and its
  `raw_data` audit copy is preserved; duplicate CSV headers rejected with a clear message;
  required-column check uses the union of keys across rows (correct for heterogeneous JSON);
  the keyword grid's term and competition filters made functional; and the remember-me auth
  key derived per-deployment instead of shipping a shared committed constant.

### 2026-07-01 — Sample dataset generated
- Built the four input files from **real** Google Ads Keyword Planner data across six
  languages (en, de, es, fr, pt, it) plus five competitor domains (Wix, Squarespace, Weebly,
  GoDaddy, Tilda): `sample-data/` — google_ads 52, search_console 79, ahrefs_organic 136,
  ahrefs_paid 112 rows, with CSV and JSON variants.
- Real search volume / CPC / competition; private account-specific metrics (GSC, Ahrefs KD)
  are clearly-labeled samples. The set deliberately includes brand terms, duplicates, junk,
  low-volume, and forbidden examples so every cleaning rule has real work. See
  `sample-data/README.md` and `docs/DATA.md`.

### 2026-07-01 — Repo, tunnel, documentation
- Initialized the git repository and pushed the skeleton to `github.com/dmsv312/test-site-pro`
  (branch `main`).
- Exposed the app publicly via a dedicated Cloudflare Tunnel: **https://sitepro.dm312sv.online**
  (verified 200 from outside; the sibling test project was left untouched).
- Established documentation conventions (see `CLAUDE.md` → "Documentation rules") and wrote
  the committed doc set: `README`, `CLAUDE`, `docs/PLAN`, `docs/DATA`, `docs/API`, `docs/WORKLOG`,
  `docs/brief/TASK`.

### 2026-07-01 — Stage 2: skeleton (Yii2 + PostgreSQL + Docker)
- Yii2 basic (2.0.55) on PostgreSQL 16, PHP 8.4; Docker topology `db` / `app` (php-fpm) /
  `web` (nginx), self-contained images, one-command `docker compose up --build` → :8100.
- Env-driven DB/mode config, pretty URLs, GD extension (for the stock captcha), and a
  dev-friendly volume layout (host-mounted source for live edits; container-managed volumes
  for `vendor` / `runtime` / `web` so a fresh clone works without host state).
- Fixed a syntax bug in the scaffolded `LoginForm.php` (unclosed docblock) that broke login.
- **Verified by hand:** all stock pages return 200; admin login works end-to-end (CSRF →
  302 → authenticated); static assets served through nginx; live code reload confirmed.

### 2026-07-01 — Stage 1: data-access spike
- Validated that we can pull **real** keyword metrics — monthly search volume, CPC,
  competition — from **Google Ads Keyword Planner** (via our Google Ads API access),
  seeding on a domain URL.
- `site.pro` → 990 keyword ideas (top volume ~165k/mo); a competitor domain → 1078 ideas
  (including competitor-brand terms — useful for brand filtering and gap analysis).
- Confirms the data strategy: real metrics where the source allows, clearly-labeled
  samples for private-account exports we cannot access. See `docs/DATA.md`.
