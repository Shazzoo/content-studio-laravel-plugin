<?php

namespace Shazzoo\ContentStudio\Support;

/**
 * Vervangt de afbeeldingsplaceholders die de Engine in de artikeltekst zet.
 *
 * De Engine levert alle typen (diagram, illustratie, en later grafieken e.d.)
 * aan in één `image_placeholders`-lijst waarin elk item een `type` heeft. De
 * oude losse lijsten blijven werken, zodat de plugin ook praat met een Engine
 * die dat veld nog niet stuurt.
 */
class ArticleImagePlaceholders
{
    private const DEFAULT_TYPE = 'image';

    /**
     * Sleutels met de oude, typegebonden lijsten en het type dat daarbij hoort.
     */
    private const LEGACY_KEYS = [
        'diagram_placeholders' => 'diagram',
        'diagrams' => 'diagram',
        'diagram_urls' => 'diagram',
        'diagram_image_urls' => 'diagram',
        'illustration_placeholders' => 'illustration',
        'illustrations' => 'illustration',
        'illustration_urls' => 'illustration',
        'illustration_image_urls' => 'illustration',
    ];

    /**
     * @param  array<string, mixed>  $item
     */
    public static function replace(string $html, array $item, ?callable $imageUrlResolver = null): string
    {
        $entries = self::collectEntries($item);

        if ($entries === []) {
            return $html;
        }

        $replacements = [];

        foreach ($entries as $id => $entry) {
            $replacement = self::render($entry, $imageUrlResolver, $id);

            if ($replacement !== null) {
                $replacements[$id] = $replacement;
            }
        }

        if ($replacements === []) {
            return $html;
        }

        // Elk `<type>-placeholder:<uuid>`-commentaar, zodat een nieuw type geen
        // aanpassing hier meer nodig heeft.
        return preg_replace_callback(
            '/<!--\s*[a-z][a-z0-9_-]*-placeholder:([0-9a-fA-F-]{36})\s*-->/i',
            static fn (array $matches): string => $replacements[strtolower($matches[1])] ?? $matches[0],
            $html
        ) ?? $html;
    }

    /**
     * Verzamelt de placeholders zonder ze al te renderen, zodat een afbeelding
     * die in meerdere lijsten staat maar één keer wordt opgehaald.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, array<string, mixed>>
     */
    private static function collectEntries(array $item): array
    {
        $entries = [];

        foreach ([$item, $item['meta'] ?? null] as $source) {
            if (! is_array($source)) {
                continue;
            }

            // De samengevoegde lijst wint, maar alleen als er ook echt iets in
            // staat: een Engine die het veld al meestuurt maar nog niet vult,
            // levert een lege array en moet op de oude lijsten terugvallen.
            $merged = $source['image_placeholders'] ?? null;

            if (is_array($merged) && $merged !== []) {
                $entries = array_merge($entries, self::entriesFromPayload($merged, self::DEFAULT_TYPE));

                continue;
            }

            foreach (self::LEGACY_KEYS as $key => $type) {
                $value = $source[$key] ?? null;

                if (! is_array($value)) {
                    continue;
                }

                $entries = array_merge($entries, self::entriesFromPayload($value, $type));
            }

            $entries = array_merge($entries, self::legacySingularEntries($source));
        }

        return $entries;
    }

    /**
     * De losse `diagram_id`/`diagram_url`-velden van oudere Engine-versies.
     *
     * @param  array<string, mixed>  $source
     * @return array<string, array<string, mixed>>
     */
    private static function legacySingularEntries(array $source): array
    {
        $entries = [];

        foreach (['diagram' => 'diagram', 'illustration' => 'illustration'] as $prefix => $type) {
            $id = self::placeholderId($source[$prefix.'_id'] ?? $source[$prefix.'_uuid'] ?? null);
            $url = self::stringValue($source[$prefix.'_url'] ?? $source[$prefix.'_image_url'] ?? null);

            if ($id !== null && $url !== null) {
                $entries[$id] = ['url' => $url, 'type' => $type];
            }
        }

        return $entries;
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<string, array<string, mixed>>
     */
    private static function entriesFromPayload(array $payload, string $fallbackType): array
    {
        $entries = [];

        foreach ($payload as $key => $entry) {
            if (is_string($key)) {
                $id = self::placeholderId($key);

                if ($id === null) {
                    continue;
                }

                $entry = is_array($entry) ? $entry : ['url' => self::stringValue($entry)];
            } else {
                if (! is_array($entry)) {
                    continue;
                }

                $id = self::placeholderId($entry['placeholder'] ?? $entry['id'] ?? $entry['uuid'] ?? $entry['placeholder_id'] ?? null);

                if ($id === null) {
                    continue;
                }
            }

            $url = self::stringValue(
                $entry['url'] ?? $entry['image_url'] ?? $entry['diagram_url'] ?? $entry['illustration_url'] ?? $entry['src'] ?? null
            );

            if ($url === null) {
                continue;
            }

            $entry['url'] = $url;
            $entry['type'] = self::normalizeType($entry['type'] ?? null) ?? $fallbackType;

            $entries[$id] = $entry;
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private static function render(array $entry, ?callable $imageUrlResolver, string $placeholderId): ?string
    {
        $url = self::stringValue($entry['url'] ?? null);

        if ($url === null) {
            return null;
        }

        $type = self::normalizeType($entry['type'] ?? null) ?? self::DEFAULT_TYPE;
        $caption = self::stringValue($entry['caption'] ?? null);
        $alt = self::stringValue($entry['alt'] ?? null) ?? $caption;

        if ($imageUrlResolver !== null) {
            $url = $imageUrlResolver($url, $placeholderId, $entry);
        }

        if ($caption === null && $alt === null) {
            return $url;
        }

        // `article-diagram` blijft staan voor sites die daar al op stylen.
        return '<figure class="article-image article-'.self::escape($type).'">'
            .'<img src="'.self::escape($url).'" alt="'.self::escape($alt ?? '').'" loading="lazy">'
            .'<figcaption>'.self::escape($caption ?? '').'</figcaption>'
            .'</figure>';
    }

    private static function normalizeType(mixed $value): ?string
    {
        $value = self::stringValue($value);

        if ($value === null) {
            return null;
        }

        $value = preg_replace('/[^a-z0-9-]/', '', strtolower($value));

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function placeholderId(mixed $value): ?string
    {
        $value = self::stringValue($value);

        if ($value === null) {
            return null;
        }

        if (preg_match('/([0-9a-fA-F-]{36})/', $value, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }

    private static function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
