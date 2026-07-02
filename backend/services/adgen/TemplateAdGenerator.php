<?php

declare(strict_types=1);

namespace app\services\adgen;

/**
 * Deterministic, offline ad generator: builds a valid responsive search ad for an ad group from
 * localized building blocks, with no external call and no AI credentials. It is the always-available
 * fallback behind {@see AdGenerationService} (stored offline-authored copy is preferred when present).
 * Stored copy now covers all six demo languages, so this engine is the safety net rather than what the
 * demo actually renders — but it must stay correct, since any missing or invalid stored entry falls
 * back to it.
 *
 * For each ad group it weaves the group's own theme (the dominant keyword token) into a couple of
 * headlines, then fills from a per-language pool of website-builder value propositions — so every ad
 * is in its group's language and mentions what the group is about. Output is capped to the RSA limits
 * and de-duplicated; the result always clears {@see RsaValidator}. Same input → same output.
 *
 * Copy is intentionally about Site.pro's product (a website builder) across the six languages in the
 * data (en/de/es/fr/it/pt); an unknown language falls back to English so a group is never left
 * without an ad.
 */
final class TemplateAdGenerator
{
    private const DEFAULT_LANGUAGE = 'en';

    /** Aim for a full-but-tidy ad without exhausting the 15-headline ceiling on repetitive copy. */
    private const TARGET_HEADLINES = 12;
    private const TARGET_DESCRIPTIONS = 4;

    /**
     * Per-language value propositions. Headlines ≤30 chars, descriptions ≤90 — kept well inside the
     * limits, and the generator filters defensively so an edited entry can never ship over-length.
     *
     * @var array<string, array{headlines: string[], descriptions: string[]}>
     */
    private const POOL = [
        'en' => [
            'headlines' => [
                'Build Your Website',
                'Free Website Builder',
                'Launch in Minutes',
                'No Coding Required',
                'Drag-and-Drop Editor',
                'Mobile-Ready Templates',
                'Start Free Today',
                'Go Online Fast',
            ],
            'descriptions' => [
                'Create a professional website in minutes with Site.pro. No coding needed — start free.',
                'Drag-and-drop builder, mobile-ready templates and hosting. Launch your site today.',
                'Build, publish and grow your website with Site.pro. Easy to use and free to start.',
                'Hundreds of templates and full customization. Your website, live in minutes.',
            ],
        ],
        'de' => [
            'headlines' => [
                'Website erstellen',
                'Kostenloser Baukasten',
                'In Minuten online',
                'Ohne Programmieren',
                'Vorlagen für jede Branche',
                'Mobil optimiert',
                'Jetzt kostenlos starten',
                'Homepage-Baukasten',
            ],
            'descriptions' => [
                'Erstellen Sie Ihre Website in Minuten mit Site.pro. Ohne Programmieren — jetzt kostenlos.',
                'Baukasten mit Vorlagen, Hosting und mobiler Optimierung. Starten Sie noch heute.',
                'Website erstellen leicht gemacht: Drag-and-drop, Vorlagen und sofort online.',
            ],
        ],
        'es' => [
            'headlines' => [
                'Crea tu sitio web',
                'Creador de webs gratis',
                'Publica en minutos',
                'Sin programar',
                'Plantillas profesionales',
                'Optimizado para móvil',
                'Empieza gratis hoy',
                'Tu web en minutos',
            ],
            'descriptions' => [
                'Crea una página web profesional en minutos con Site.pro. Sin programar y gratis.',
                'Creador con plantillas, hosting y diseño adaptable. Empieza hoy mismo.',
                'Publica tu web fácilmente: arrastrar y soltar, plantillas y hosting incluido.',
            ],
        ],
        'fr' => [
            'headlines' => [
                'Créez votre site web',
                'Créateur de site gratuit',
                'En ligne en minutes',
                'Sans coder',
                'Modèles professionnels',
                'Optimisé pour mobile',
                'Commencez gratuitement',
                'Votre site en minutes',
            ],
            'descriptions' => [
                'Créez un site web professionnel en minutes avec Site.pro. Sans coder et gratuit.',
                'Créateur avec modèles, hébergement et design responsive. Lancez-vous aujourd’hui.',
                'Publiez votre site facilement : glisser-déposer, modèles et hébergement inclus.',
            ],
        ],
        'it' => [
            'headlines' => [
                'Crea il tuo sito web',
                'Website builder gratis',
                'Online in pochi minuti',
                'Senza programmare',
                'Modelli professionali',
                'Ottimizzato per mobile',
                'Inizia gratis oggi',
                'Il tuo sito in minuti',
            ],
            'descriptions' => [
                'Crea un sito web professionale in minuti con Site.pro. Senza programmare e gratis.',
                'Editor con modelli, hosting e design responsive. Inizia oggi stesso.',
                'Pubblica il tuo sito facilmente: trascina e rilascia, modelli inclusi.',
            ],
        ],
        'pt' => [
            'headlines' => [
                'Crie seu site',
                'Criador de sites grátis',
                'No ar em minutos',
                'Sem programar',
                'Modelos profissionais',
                'Otimizado para celular',
                'Comece grátis hoje',
                'Seu site em minutos',
            ],
            'descriptions' => [
                'Crie um site profissional em minutos com o Site.pro. Sem programar e grátis.',
                'Criador com modelos, hospedagem e design responsivo. Comece hoje mesmo.',
                'Publique seu site facilmente: arrastar e soltar, modelos inclusos.',
            ],
        ],
    ];

    /** Build the ad copy for one ad group (its URL is applied by the service, not here). */
    public function generate(string $language, string $theme, string $themeKey): AdContent
    {
        $lang = isset(self::POOL[$language]) ? $language : self::DEFAULT_LANGUAGE;
        $pool = self::POOL[$lang];

        // Theme headlines first (relevance to the group's keywords), then the language pool.
        $headlines = $this->capList(
            [...$this->themeHeadlines($theme, $themeKey), ...$pool['headlines']],
            self::TARGET_HEADLINES,
            RsaValidator::HEADLINE_MAX,
        );
        $descriptions = $this->capList(
            $pool['descriptions'],
            self::TARGET_DESCRIPTIONS,
            RsaValidator::DESCRIPTION_MAX,
        );

        return new AdContent($lang, $headlines, $descriptions);
    }

    /**
     * Headlines derived from the ad group's theme, so the ad echoes the keywords it targets. The
     * generic "General" bucket has no meaningful theme word, so it contributes none.
     *
     * @return string[]
     */
    private function themeHeadlines(string $theme, string $themeKey): array
    {
        if ($themeKey === '' || $themeKey === 'general') {
            return [];
        }

        $titled = mb_convert_case($theme, MB_CASE_TITLE, 'UTF-8');

        return [$titled, $titled . ' · Site.pro'];
    }

    /**
     * De-duplicate (case-insensitive), drop empty or over-length items, and cap to `$limit` while
     * preserving order — a deterministic, always-valid slice of the candidates.
     *
     * @param string[] $items
     * @return string[]
     */
    private function capList(array $items, int $limit, int $maxLen): array
    {
        $out = [];
        $seen = [];
        foreach ($items as $item) {
            $item = trim($item);
            // Reject empty, invalid-UTF-8, over-length, or control-char items so the generator's
            // output always clears RsaValidator — even a theme-derived headline whose theme carried a
            // stray control byte from the source data (the pool is always clean; only themes can trip
            // this). Mirrors RsaValidator::isClean.
            if ($item === '' || !mb_check_encoding($item, 'UTF-8')) {
                continue;
            }
            if (mb_strlen($item, 'UTF-8') > $maxLen || preg_match('/[\x00-\x1F\x7F]/u', $item) === 1) {
                continue;
            }
            $key = mb_strtolower($item, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $item;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
