# Work log

> Single source of truth for status. Newest entries on top. Every build stage lands here.

## Current status

- **Done:** stage 5 — preparation for Google Ads. Over the cleaned keywords it drops terms
  Site.pro **already runs** (the `google_ads` source ⇒ only net-new remain) and any **forbidden**
  term, keeps one canonical per duplicate group (highest volume — volume is not summed), then
  **groups** the survivors into one campaign per language with **themed ad groups** (a
  frequency-based token clusterer). Admin **funnel + campaign preview**, a `yii prepare/run`
  console command, and unit tests for the new rules. 154 cleaned → 47 already-used dropped →
  **107 prepared** → **19 ad groups across 6 languages**; idempotent. While building this we found
  and fixed a stage-4↔5 dedup drift bug: cleaning is now a pure function of the imported data
  (a run resets the whole downstream), so repeated clean→prepare cycles are stable. Reviewed
  adversarially (one latent `ad_ready` link-preservation finding fixed).
- **Next:** stage 6 — ad generation per ad group (in its language, correct target URL) + cache.
- **Live:** https://sitepro.dm312sv.online · local http://127.0.0.1:8100 (admin login from `.env`)

## Stages

| # | Stage | Status |
|---|-------|--------|
| 1 | Data-access spike (validate real keyword metrics) | ✅ done |
| 2 | Skeleton: Yii2 + PostgreSQL + Docker, admin login | ✅ done |
| 3 | Import (CSV/JSON) + `keyword` model + admin GridView | ✅ done |
| 4 | Cleaning pipeline + funnel dashboard | ✅ done |
| 5 | Prepare for Google Ads (already-used/forbidden/merge/group by language+theme) | ✅ done |
| 6 | Ad generation (per language, correct URL) + cache | planned |
| 7 | Campaign preview + Google Ads Editor CSV export | planned |
| 8 | Real data collection → input files + labeled samples | ✅ done (early) |
| 9 | Deploy hardening + smoke | planned |

## Journal

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
