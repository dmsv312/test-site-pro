# Site.pro — Marketing Automation (Keyword → Google Ads)

Dockerized Yii2 + PostgreSQL platform that ingests keyword data (Google Ads,
Search Console, Ahrefs organic / competitor paid), cleans it, prepares Google
Ads campaigns grouped by language, and exports a Google Ads Editor import file.

> Status: work in progress — see commit history for what's implemented.

## Stack

- Yii2 (basic) 2.0.55, PHP 8.4
- PostgreSQL 16
- Docker: `db` / `app` (php-fpm) / `web` (nginx)

## Quick start

```bash
docker compose up --build -d      # → http://127.0.0.1:8100
```

Admin login: `admin` / `admin`.

## Layout

- `backend/` — Yii2 application (thin controllers, service layer)
- `docker/` — nginx config
- `docker-compose.yml` — full stack, one command

## Pipeline (target)

1. Import keyword sources (CSV / JSON; API later)
2. Admin area — all data visible
3. Clean — remove junk, duplicates, brand terms, low-volume
4. Prepare for Google Ads — drop already-used / forbidden, merge, group by language
5. Generate ads (per language, correct target URL) and export a Google Ads Editor file
