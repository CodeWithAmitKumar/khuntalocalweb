<?php
/**
 * KhuntaLocal — SEO helpers.
 *
 * Builds the per-page meta array consumed by partials/head.php (title, meta
 * description, canonical, Open Graph, Twitter card) plus JSON-LD structured
 * data for articles. Deeper SEO (XML sitemap, AMP, etc.) lands in Phase 5.
 */

declare(strict_types=1);

if (!function_exists('default_meta')) {
    /**
     * @return array<string,mixed>
     */
    function default_meta(array $overrides = []): array
    {
        $name    = site_name();
        $tagline = (string) setting('site_tagline', 'Local news from Khunta & Mayurbhanj');

        $defaults = [
            'title'       => $name . ' — ' . $tagline,
            'description' => 'KhuntaLocal is a hyperlocal community news platform for Khunta and the surrounding areas of Mayurbhanj, Odisha. Read and share verified local news.',
            'canonical'   => current_url(),
            'image'       => '',
            'type'        => 'website',
            'robots'      => 'index,follow',
            'published_time' => null,
            'author'      => null,
            'jsonld'      => null,
        ];
        return array_merge($defaults, $overrides);
    }
}

if (!function_exists('current_url')) {
    function current_url(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Prefer configured base host over the raw Host header (avoids spoofing).
        $base = rtrim((string) config('app.url', ''), '/');
        return $base . $uri;
    }
}

if (!function_exists('news_meta')) {
    /**
     * Build a meta array for a news article row (expects joined fields like
     * author_name, category_name).
     *
     * @param array<string,mixed> $news
     * @return array<string,mixed>
     */
    function news_meta(array $news): array
    {
        $title = $news['seo_title'] ?: ($news['title'] . ' | ' . site_name());
        $desc  = $news['meta_description'] ?: str_excerpt($news['summary'] ?: $news['body'], 160);
        $image = '';
        if (!empty($news['cover_path'])) {
            $image = upload_url($news['cover_path']);
        }

        $meta = default_meta([
            'title'          => $title,
            'description'    => $desc,
            'canonical'      => $news['canonical_url'] ?: news_url($news['slug']),
            'image'          => $image,
            'type'           => 'article',
            'published_time' => $news['published_at'] ?? null,
            'author'         => $news['author_name'] ?? null,
        ]);
        $meta['jsonld'] = json_ld_article($news);
        return $meta;
    }
}

if (!function_exists('json_ld_article')) {
    /**
     * NewsArticle structured data. We present it as community-reviewed content,
     * never asserting absolute factual certainty.
     *
     * @param array<string,mixed> $news
     * @return array<string,mixed>
     */
    function json_ld_article(array $news): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'NewsArticle',
            'headline' => (string) $news['title'],
            'datePublished' => !empty($news['published_at']) ? date('c', strtotime((string) $news['published_at'])) : null,
            'dateModified'  => !empty($news['updated_at']) ? date('c', strtotime((string) $news['updated_at'])) : null,
            'mainEntityOfPage' => news_url($news['slug']),
            'publisher' => [
                '@type' => 'Organization',
                'name'  => site_name(),
            ],
        ];
        if (!empty($news['author_name'])) {
            $data['author'] = ['@type' => 'Person', 'name' => (string) $news['author_name']];
        }
        if (!empty($news['cover_path'])) {
            $data['image'] = [upload_url($news['cover_path'])];
        }
        if (!empty($news['summary'])) {
            $data['description'] = (string) $news['summary'];
        }
        return array_filter($data, static fn($v) => $v !== null);
    }
}

if (!function_exists('render_meta_tags')) {
    /**
     * Echo the <title>, meta, Open Graph, Twitter and JSON-LD tags from a meta
     * array. Called by partials/head.php.
     *
     * @param array<string,mixed> $meta
     */
    function render_meta_tags(array $meta): void
    {
        $title = (string) ($meta['title'] ?? site_name());
        $desc  = (string) ($meta['description'] ?? '');
        $url   = (string) ($meta['canonical'] ?? current_url());
        $image = (string) ($meta['image'] ?? '');
        $type  = (string) ($meta['type'] ?? 'website');

        echo '<title>' . e($title) . "</title>\n";
        echo '    <meta name="description" content="' . e_attr($desc) . "\">\n";
        echo '    <meta name="robots" content="' . e_attr((string) ($meta['robots'] ?? 'index,follow')) . "\">\n";
        echo '    <link rel="canonical" href="' . e_attr($url) . "\">\n";

        // Open Graph
        echo '    <meta property="og:site_name" content="' . e_attr(site_name()) . "\">\n";
        echo '    <meta property="og:title" content="' . e_attr($title) . "\">\n";
        echo '    <meta property="og:description" content="' . e_attr($desc) . "\">\n";
        echo '    <meta property="og:type" content="' . e_attr($type) . "\">\n";
        echo '    <meta property="og:url" content="' . e_attr($url) . "\">\n";
        if ($image !== '') {
            echo '    <meta property="og:image" content="' . e_attr($image) . "\">\n";
        }
        if (!empty($meta['published_time'])) {
            echo '    <meta property="article:published_time" content="' . e_attr(date('c', strtotime((string) $meta['published_time']))) . "\">\n";
        }

        // Twitter / X
        echo '    <meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . "\">\n";
        echo '    <meta name="twitter:title" content="' . e_attr($title) . "\">\n";
        echo '    <meta name="twitter:description" content="' . e_attr($desc) . "\">\n";
        if ($image !== '') {
            echo '    <meta name="twitter:image" content="' . e_attr($image) . "\">\n";
        }

        // JSON-LD
        if (!empty($meta['jsonld']) && is_array($meta['jsonld'])) {
            echo '    <script type="application/ld+json">'
                . json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . "</script>\n";
        }
    }
}
