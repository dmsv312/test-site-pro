# CLAUDE.md — repo context for working sessions

Marketing automation platform for **Site.pro**: import keyword data, clean it, prepare
Google Ads campaigns grouped by language, and export a Google Ads Editor file. Built
with AI-assisted coding (#vibecoding), reviewed and owned by the author. Keep this file
current.

- Task: [`docs/brief/TASK.md`](docs/brief/TASK.md)
- Architecture, plan & decisions: [`docs/PLAN.md`](docs/PLAN.md)
- Data sources & schema: [`docs/DATA.md`](docs/DATA.md)
- Import/export contracts (planned): [`docs/API.md`](docs/API.md)
- Work journal (status source of truth): [`docs/WORKLOG.md`](docs/WORKLOG.md)

## Stack

- **Yii2** basic 2.0.55, **PHP 8.4**
- **PostgreSQL 16**
- **Docker Compose**: `db` / `app` (php-fpm) / `web` (nginx); one command `docker compose up --build`

## Architecture (layers)

```
Sources (CSV/JSON now, API later)
  → Import adapters  → normalize into one `keyword` table (one row per term, with provenance)
  → Cleaning pipeline (junk → dedup → brand → volume) — every drop logged with a reason
  → Preparation (drop already-used/forbidden → keep one canonical per dup group → group by
    language into one campaign each, with themed ad groups)
  → Ad generation (one RSA per ad group, in its language + the group's URL): stored offline-authored
    copy preferred, deterministic per-language template fallback, all RSA-validated; URL from the
    ad group, never the copy
  → Preview + export (Google Ads Editor CSV)
```

Thin controllers → **service layer** holds the logic; ActiveRecord models for data. The
admin area (login-gated) shows every stage; a funnel shows how many keywords survive each
step and why the rest were dropped.

The **whole app is login-gated**: `defaultRoute` is `import/index`, so the home page opens the
import dashboard, and a guest hitting any page is redirected to login. There are no public
marketing pages — the stock Yii home/about/contact scaffold was removed.

## Structure

```
backend/                Yii2 application
  config/               web.php, console.php, db.php (env-driven), params.php (lang→URL map)
  controllers/          SiteController (login/logout/error); ImportController, CleaningController, PrepareController, AdsController, ExportController, RulesController (login-gated admin)
  commands/             console controllers (import/samples, import/file; clean/run; prepare/run; adgen/run; export/file)
  models/               ActiveRecord (Keyword, ImportBatch, AdGroup, GeneratedAd, RuleConfig, BrandTerm/ForbiddenTerm via TermListRecord) + form models (UploadForm, KeywordSearch, User, Login)
  migrations/           schema — import_batch + keyword (stage 3); rule_config + brand_term + forbidden_term (stage 4); ad_group + keyword.ad_group_id (stage 5); generated_ad (stage 6)
  services/             import/ (readers, adapters, ImportService); cleaning/ (JunkRule, BrandRule, VolumeRule, CleaningService); preparation/ (AlreadyUsedRule, ForbiddenRule, ThemeClusterer, GroupingService, PreparationService); adgen/ (AdContent, RsaValidator, TemplateAdGenerator, StoredAdSource, AdGenerationService); export/ (GoogleAdsEditorExport — pure CSV format; ExportService)
  views/                site/ (login, error), import/ (dashboard, keywords), cleaning/ (funnel), prepare/ (funnel + campaign preview), ads/ (RSA preview), export/ (preview + download), rules/ (thresholds + lists)
  web/                  front controller + published assets
  docker/entrypoint.sh  waits for DB, refreshes static, runs migrations, starts php-fpm
  Dockerfile            php:8.4-fpm + ext (pdo_pgsql, intl, gd, …) + composer install
docker/nginx.conf       serves static + proxies PHP to app:9000
docker-compose.yml      full stack; web on 127.0.0.1:8100
docs/                   PLAN, DATA, API, WORKLOG, brief/TASK
```

## Commands

```bash
cp .env.example .env                      # first run: fill in config (admin login, DB, cookie key)
docker compose up --build -d              # full stack → http://127.0.0.1:8100 (admin login from .env)
docker compose exec app php yii migrate   # run migrations manually (also run on container start)
docker compose exec app php yii import/samples   # import the four sample-data files
docker compose exec app php yii clean/run        # run the cleaning pipeline (junk→dedup→brand→volume); resets stages 5–6 (then run prepare + adgen)
docker compose exec app php yii prepare/run      # prepare for Google Ads (drop already-used/forbidden → group by language+theme); resets stage 6 (then run adgen)
docker compose exec app php yii adgen/run        # generate one RSA per ad group (stored copy preferred, template fallback)
docker compose exec app php yii export/file      # write the Google Ads Editor CSV (default: runtime/export/…)
docker compose logs -f app                # app (php-fpm) logs
docker compose down                       # stop (keep data);  down -v to reset volumes
```

Config — including the admin username/password — comes from `.env` (see `.env.example`);
nothing sensitive is hard-coded in PHP. Code is bind-mounted from the host: edits under
`backend/` are live without a rebuild. Rebuild only when `Dockerfile`/composer deps change.

## Conventions

- Thin controllers → services → views. Business logic lives in `backend/services/`, not in
  controllers or spread across models.
- A migration for every schema change; seeders are idempotent.
- Cleaning rules are small, single-purpose classes; each records **why** a keyword was dropped.
- **Real vs sample data is always labeled** (see `docs/DATA.md`). Don't overclaim.
- Env-driven config (DB, mode, keys) — no environment values hard-coded in committed code.

## Documentation rules

- **Committed docs in English; internal notes in Russian.**
- **Two tiers.** Committed (employer-facing, in git): `README.md`, `CLAUDE.md`, `docs/*`.
  Internal (gitignored): `implementation-plan.md` — data provenance, credential handling,
  presentation strategy. Never commit internal notes.
- **Update docs in the same commit as the code they describe.** No stale docs.
- **`docs/WORKLOG.md` is the single source of truth for status** (last done / next step).
- **Every significant decision → `docs/PLAN.md` → "Decisions"** (context → decision → consequences).
- **Never commit:** secrets, third-party account credentials, private account data, or
  internal strategy. Label real vs sample. No unverifiable claims.
- Diagrams in Mermaid where they help (they render on GitHub).

## Git / commits

- Repo: `github.com/dmsv312/test-site-pro` (SSH). Isolated from the parent workspace repo.
- Commits **without** a `Co-Authored-By` trailer, author `Dmitrii <dm.sv312@gmail.com>`,
  in logical stages (mirrored in `docs/WORKLOG.md`).

## Deploy

- `docker compose` in production mode (`YII_ENV=prod`). `web` listens on `127.0.0.1:8100`,
  exposed via a dedicated Cloudflare Tunnel (isolated account).
- Live demo: **https://sitepro.dm312sv.online**
- **Hardening (decision 33):** auth cookies (session / CSRF / remember-me) are
  `Secure; HttpOnly; SameSite=Lax` when `APP_URL` is `https://`; `COOKIE_VALIDATION_KEY` is
  **required in prod** (boot fails without it — no shared key in git); nginx sets
  `server_tokens off`, hides `X-Powered-By`, and adds `X-Content-Type-Options` /
  `X-Frame-Options: DENY` / `Referrer-Policy`.
- **Ops caveat:** `docker/nginx.conf` is a **single-file bind mount** (binds an inode). An
  atomic-rename edit leaves the container on the stale inode, so `docker compose restart web`
  won't apply it — run `docker compose up -d --force-recreate web`. The `./backend` **directory**
  mount reflects PHP edits live (no rebuild).

## Current status

See [`docs/WORKLOG.md`](docs/WORKLOG.md).
