# Work log

> Single source of truth for status. Newest entries on top. Every build stage lands here.

## Current status

- **Done:** project skeleton — Yii2 + PostgreSQL + Docker, running locally and via the
  public tunnel; data-access validated against Google Ads Keyword Planner.
- **Next:** stage 3 — file import (CSV/JSON), the `keyword` data model, and an admin
  view (GridView) that shows all imported data.
- **Live:** https://sitepro.dm312sv.online · local http://127.0.0.1:8100 (`admin` / `admin`)

## Stages

| # | Stage | Status |
|---|-------|--------|
| 1 | Data-access spike (validate real keyword metrics) | ✅ done |
| 2 | Skeleton: Yii2 + PostgreSQL + Docker, admin login | ✅ done |
| 3 | Import (CSV/JSON) + `keyword` model + admin GridView | ← current |
| 4 | Cleaning pipeline + funnel dashboard | planned |
| 5 | Prepare for Google Ads (already-used/forbidden/merge/group by language) | planned |
| 6 | Ad generation (per language, correct URL) + cache | planned |
| 7 | Campaign preview + Google Ads Editor CSV export | planned |
| 8 | Real data collection → input files + labeled samples | planned |
| 9 | Deploy hardening + smoke | planned |

## Journal

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
