# Task — original assignment (as provided)

> Verbatim copy of the assignment. Do not edit; record interpretation and decisions
> in [`docs/PLAN.md`](../PLAN.md).

---

#vibecoding

We neede marketing automation platform made 100% with vibecode (0 code) using Yii2. You are free to put your ideas to make the algorithm better.

1. Generate few data sources (CSV or JSON) as input:

google_ads_keywords.csv — Site.pro keywords used in Ads

search_console_queries.csv — Site.pro keywords used in Search Console

ahrefs_organic_keywords.csv — Site.pro keywords used in Ahrefs

ahrefs_paid_keywords.csv — keywords used by competitors (from Ahrefs)

2. Create a system that allows import of these files (should accept CSV, JSON. Later we will use API)

1. System should have Admin area, where all data is visible

2. Remove bad keywords

Remove Junk

Remove dublicated and Brand names

Filter by volume

3. Prepare keywords for GAds:

Remove Already used and Forbidden keywords

Merged dublicated keywords

Group keywords by languages

Generate Ads for them (on their on langauge and correct target URL) by GAds format

4. Generate a preview and file to import for GAds

5. Provide the URL where we can test it: upload, admin-area, preview and export ability
