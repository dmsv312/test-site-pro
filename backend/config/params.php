<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',

    // Language → localized Site.pro landing URL. Verified live on 2026-07-01:
    // de/es/fr/it have dedicated localized pages; en and pt resolve to their real
    // targets (pt has no /pt page — the live site serves Portuguese at /pt-br/).
    // Used as the default target URL when grouping ads by language (stage 6); the
    // admin area can override per language once canonical URLs are provided.
    'languageUrlMap' => [
        'en' => 'https://site.pro/',
        'de' => 'https://site.pro/de/',
        'es' => 'https://site.pro/es/',
        'fr' => 'https://site.pro/fr/',
        'it' => 'https://site.pro/it/',
        'pt' => 'https://site.pro/pt-br/',
    ],
    'defaultLandingUrl' => 'https://site.pro/',

    // Offline-authored ad copy preferred by stage-6 generation (decision 3): a committed JSON file
    // keyed by "{language}:{theme_key}". Shipped as an artifact in sample-data (mounted read-only at
    // /opt/sample-data); anything without an entry falls back to the template engine. Missing file =
    // template-only, no error.
    'storedAdsPath' => '/opt/sample-data/generated-ads.json',
];
