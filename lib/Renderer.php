<?php

namespace TypechoPlugin\MediaShelf\Lib;

class Renderer
{
    public static function renderPage()
    {
        $title = self::pageText('shelfTitle', 'Media Shelf');
        $siteTitle = self::siteTitle();
        echo '<!doctype html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . self::h($title) . ($siteTitle !== '' ? ' - ' . self::h($siteTitle) : '') . '</title>';
        echo '</head>';
        echo '<body>';
        echo self::renderShelf();
        echo '</body>';
        echo '</html>';
    }

    public static function renderDetailPage($slug)
    {
        $title = self::siteTitle();
        echo '<!doctype html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Media Shelf Detail' . ($title !== '' ? ' - ' . self::h($title) : '') . '</title>';
        echo '</head>';
        echo '<body>';
        echo self::renderDetail($slug);
        echo '</body>';
        echo '</html>';
    }

    public static function renderDetail($slug)
    {
        $repository = new WorkRepository();
        $work = $repository->findPublishedBySlug($slug);
        $html = '<section class="mediashelf mediashelf-detail" aria-labelledby="mediashelf-detail-title">';
        $html .= self::assets();
        $html .= '<div class="mediashelf__inner">';
        if (!$work) {
            $html .= '<p class="mediashelf__empty">This published work was not found.</p>';
        } else {
            $html .= self::detail($work, $repository);
        }
        $html .= '</div>';
        $html .= '</section>';

        return $html;
    }

    public static function renderShelf(array $filters = [])
    {
        $repository = new WorkRepository();
        $filters = self::requestFilters($filters);
        $limit = self::itemsPerPage();
        $works = $repository->findPublished($filters, $limit);
        $facets = $repository->publicFacets();
        $display = self::displayOptions();
        $eyebrow = self::pageText('shelfEyebrow', 'Personal Collection');
        $title = self::pageText('shelfTitle', 'Media Shelf');
        $summary = self::pageText('shelfSummary', 'A collection for works I like.');

        $html = '<section class="mediashelf" aria-labelledby="mediashelf-title">';
        $html .= self::assets();
        $html .= '<div class="mediashelf__inner">';
        $html .= '<header class="mediashelf__header">';
        $html .= '<p class="mediashelf__eyebrow">' . self::h($eyebrow) . '</p>';
        $html .= '<h1 id="mediashelf-title">' . self::h($title) . '</h1>';
        $html .= '<p class="mediashelf__summary">' . self::h($summary) . '</p>';
        $html .= '</header>';
        $html .= self::filters($filters, $facets);

        if (!$works) {
            $html .= '<p class="mediashelf__empty">No published works match these filters yet.</p>';
        } else {
            $html .= '<div class="mediashelf__grid">';
            foreach ($works as $work) {
                $html .= self::card($work, $repository, $display);
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</section>';

        return $html;
    }

    private static function requestFilters(array $filters)
    {
        foreach (['category', 'tag', 'search'] as $key) {
            if (!isset($filters[$key]) && isset($_GET[$key])) {
                $filters[$key] = trim((string) $_GET[$key]);
            }
        }

        return [
            'category' => isset($filters['category']) ? (string) $filters['category'] : '',
            'tag' => isset($filters['tag']) ? (string) $filters['tag'] : '',
            'search' => isset($filters['search']) ? trim((string) $filters['search']) : '',
        ];
    }

    private static function filters(array $filters, array $facets)
    {
        $html = '<form class="mediashelf__filters" method="get">';
        $html .= '<label><span>Search</span><input type="search" name="search" value="' . self::h($filters['search']) . '"></label>';
        $html .= '<label><span>Category</span><select name="category">';
        $html .= '<option value="">All categories</option>';
        foreach ($facets['categories'] as $value => $label) {
            $selected = $filters['category'] === $value ? ' selected' : '';
            $html .= '<option value="' . self::h($value) . '"' . $selected . '>' . self::h($label) . '</option>';
        }
        $html .= '</select></label>';
        $html .= '<label><span>Tag</span><select name="tag">';
        $html .= '<option value="">All tags</option>';
        foreach ($facets['tags'] as $tag) {
            $selected = $filters['tag'] === $tag ? ' selected' : '';
            $html .= '<option value="' . self::h($tag) . '"' . $selected . '>' . self::h($tag) . '</option>';
        }
        $html .= '</select></label>';
        $html .= '<button type="submit">Filter</button>';
        $html .= '<a href="' . self::h(self::basePath()) . '">Reset</a>';
        $html .= '</form>';

        return $html;
    }

    private static function card(array $work, WorkRepository $repository, array $display)
    {
        $category = isset($work['category'], WorkRepository::CATEGORIES[$work['category']])
            ? WorkRepository::CATEGORIES[$work['category']]
            : 'Other';
        $favorite = isset($work['favorite_level']) ? (string) $work['favorite_level'] : 'normal';
        $tags = $repository->decodeJsonList(isset($work['tags_json']) ? $work['tags_json'] : '');
        $creators = $repository->decodeJsonList(isset($work['creators_json']) ? $work['creators_json'] : '');
        $cover = self::safeUrl(isset($work['cover_url']) ? $work['cover_url'] : '');
        $blogUrl = self::safeUrl(isset($work['blog_url']) ? $work['blog_url'] : '');
        $cardUrl = self::cardUrl($work, $repository, $blogUrl);

        $html = '<article class="mediashelf-card">';
        $html .= '<div class="mediashelf-card__cover">';
        if ($cardUrl !== '') {
            $html .= '<a class="mediashelf-card__cover-link" ' . self::externalLinkAttributes($cardUrl) . '>';
        }
        if ($cover !== '') {
            $html .= '<img src="' . self::h($cover) . '" alt="' . self::h((string) $work['title']) . ' cover" loading="lazy">';
        } else {
            $html .= '<div class="mediashelf-card__placeholder" aria-hidden="true">' . self::h(self::initial((string) $work['title'])) . '</div>';
        }
        if ($cardUrl !== '') {
            $html .= '</a>';
        }
        $html .= '</div>';
        $html .= '<div class="mediashelf-card__body">';
        $meta = [];
        if ($display['category']) {
            $meta[] = self::h($category);
        }
        if ($favorite !== '' && $favorite !== 'normal') {
            $meta[] = self::h(ucfirst(str_replace('_', ' ', $favorite)));
        }
        if ($meta) {
            $html .= '<div class="mediashelf-card__meta">';
            foreach ($meta as $item) {
                $html .= '<span>' . $item . '</span>';
            }
            $html .= '</div>';
        }
        if ($display['title']) {
            $html .= '<h2>';
            if ($cardUrl !== '') {
                $html .= '<a ' . self::externalLinkAttributes($cardUrl) . '>' . self::h((string) $work['title']) . '</a>';
            } else {
                $html .= self::h((string) $work['title']);
            }
            $html .= '</h2>';
        }
        if ($display['originalTitle'] && !empty($work['original_title'])) {
            $html .= '<p class="mediashelf-card__original">' . self::h($work['original_title']) . '</p>';
        }
        if ($display['creators'] && $creators) {
            $html .= '<p class="mediashelf-card__creators">' . self::h(implode(', ', $creators)) . '</p>';
        }
        if ($display['reviewText'] && !empty($work['review_text'])) {
            $html .= '<p class="mediashelf-card__review">' . self::h(self::excerpt($work['review_text'], 150)) . '</p>';
        }
        if ($display['tags'] && $tags) {
            $html .= '<div class="mediashelf-card__tags">';
            foreach ($tags as $tag) {
                $html .= '<span>' . self::h($tag) . '</span>';
            }
            $html .= '</div>';
        }
        if ($display['linkedPost']) {
            if ($blogUrl !== '') {
                $html .= '<a class="mediashelf-card__post" ' . self::externalLinkAttributes($blogUrl) . '>Related post</a>';
            } elseif (!empty($work['blog_cid'])) {
                $preview = $repository->blogPreview((int) $work['blog_cid']);
                if ($preview) {
                    $html .= '<a class="mediashelf-card__post" ' . self::externalLinkAttributes((string) $preview['url']) . '>' . self::h($preview['title'] ?: 'Related post') . '</a>';
                    if (!empty($preview['excerpt'])) {
                        $html .= '<p class="mediashelf-card__post-excerpt">' . self::h($preview['excerpt']) . '</p>';
                    }
                } else {
                    $html .= '<p class="mediashelf-card__post muted">Related post #' . self::h((int) $work['blog_cid']) . '</p>';
                }
            }
        }
        $html .= '</div>';
        $html .= '</article>';

        return $html;
    }

    private static function cardUrl(array $work, WorkRepository $repository, $blogUrl)
    {
        if ($blogUrl !== '') {
            return $blogUrl;
        }

        if (!empty($work['blog_cid'])) {
            $preview = $repository->blogPreview((int) $work['blog_cid']);
            if ($preview && !empty($preview['url'])) {
                return self::safeHref((string) $preview['url']);
            }
        }

        return self::sourceUrl(isset($work['source_payload_json']) ? (string) $work['source_payload_json'] : '');
    }

    private static function sourceUrl($json)
    {
        if (trim((string) $json) === '') {
            return '';
        }

        $payload = json_decode((string) $json, true);
        if (!is_array($payload)) {
            return '';
        }

        $sourceUrl = isset($payload['source_url']) ? self::safeHref((string) $payload['source_url']) : '';
        if ($sourceUrl !== '') {
            return $sourceUrl;
        }

        if (isset($payload['mapped']) && is_array($payload['mapped']) && isset($payload['mapped']['source_url'])) {
            return self::safeHref((string) $payload['mapped']['source_url']);
        }

        return '';
    }

    private static function detail(array $work, WorkRepository $repository)
    {
        $category = isset($work['category'], WorkRepository::CATEGORIES[$work['category']])
            ? WorkRepository::CATEGORIES[$work['category']]
            : 'Other';
        $cover = self::safeUrl(isset($work['cover_url']) ? $work['cover_url'] : '');
        $tags = $repository->decodeJsonList(isset($work['tags_json']) ? $work['tags_json'] : '');
        $creators = $repository->decodeJsonList(isset($work['creators_json']) ? $work['creators_json'] : '');

        $html = '<article class="mediashelf-detail__layout">';
        $html .= '<div class="mediashelf-detail__cover">';
        if ($cover !== '') {
            $html .= '<img src="' . self::h($cover) . '" alt="' . self::h((string) $work['title']) . ' cover">';
        } else {
            $html .= '<div class="mediashelf-card__placeholder" aria-hidden="true">' . self::h(self::initial((string) $work['title'])) . '</div>';
        }
        $html .= '</div>';
        $html .= '<div class="mediashelf-detail__body">';
        $html .= '<p class="mediashelf__eyebrow">' . self::h($category) . '</p>';
        $html .= '<h1 id="mediashelf-detail-title">' . self::h((string) $work['title']) . '</h1>';
        if (!empty($work['original_title'])) {
            $html .= '<p class="mediashelf-card__original">' . self::h($work['original_title']) . '</p>';
        }
        if ($creators) {
            $html .= '<p class="mediashelf-card__creators">' . self::h(implode(', ', $creators)) . '</p>';
        }
        if (!empty($work['description'])) {
            $html .= '<div class="mediashelf-detail__text">' . nl2br(self::h($work['description'])) . '</div>';
        }
        if (!empty($work['review_text'])) {
            $html .= '<h2>Review</h2><div class="mediashelf-detail__text">' . nl2br(self::h($work['review_text'])) . '</div>';
        }
        if ($tags) {
            $html .= '<div class="mediashelf-card__tags">';
            foreach ($tags as $tag) {
                $html .= '<span>' . self::h($tag) . '</span>';
            }
            $html .= '</div>';
        }
        $html .= '<p><a class="mediashelf-card__post" href="' . self::h(self::baseShelfPath()) . '">Back to shelf</a></p>';
        $html .= '</div>';
        $html .= '</article>';

        return $html;
    }

    private static function excerpt($value, $length)
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)));
        if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $length) {
            return mb_substr($value, 0, $length, 'UTF-8') . '...';
        }

        if (!function_exists('mb_strlen') && strlen($value) > $length) {
            return substr($value, 0, $length) . '...';
        }

        return $value;
    }

    private static function initial($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '?';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, 1, 'UTF-8');
        }

        return substr($value, 0, 1);
    }

    private static function itemsPerPage()
    {
        $value = 48;
        $config = self::config();
        if ($config && isset($config->itemsPerPage)) {
            $value = max(1, min(200, (int) $config->itemsPerPage));
        }

        return $value;
    }

    private static function displayOptions()
    {
        $config = self::config();

        return [
            'tags' => self::enabled($config, 'showTags', false),
            'title' => self::enabled($config, 'showTitle', true),
            'category' => self::enabled($config, 'showCategory', true),
            'originalTitle' => self::enabled($config, 'showOriginalTitle', false),
            'creators' => self::enabled($config, 'showCreators', false),
            'linkedPost' => self::enabled($config, 'showLinkedPost', true),
            'reviewText' => self::enabled($config, 'showReviewText', true),
        ];
    }

    private static function enabled($config, $key, $default)
    {
        if (!$config || !isset($config->{$key})) {
            return (bool) $default;
        }

        return (string) $config->{$key} === '1';
    }

    private static function pageText($key, $default)
    {
        $config = self::config();
        if (!$config || !isset($config->{$key})) {
            return $default;
        }

        $value = trim((string) $config->{$key});
        return $value === '' ? $default : $value;
    }

    private static function basePath()
    {
        $path = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri !== '') {
            return strtok($uri, '?');
        }

        return $path ?: '/works';
    }

    private static function baseShelfPath()
    {
        $path = self::basePath();
        return preg_replace('#/[^/]+/?$#', '', $path) ?: '/works';
    }

    private static function detailUrl($slug)
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return '';
        }

        return rtrim(self::basePath(), '/') . '/' . rawurlencode($slug);
    }

    private static function siteTitle()
    {
        try {
            if (class_exists('\Widget\Options')) {
                $options = \Widget\Options::alloc();
                return isset($options->title) ? (string) $options->title : '';
            }
        } catch (\Throwable $e) {
            return '';
        }

        return '';
    }

    private static function config()
    {
        try {
            if (class_exists('\Widget\Options')) {
                return \Widget\Options::alloc()->plugin('MediaShelf');
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private static function safeUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    private static function safeHref($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return $url;
        }

        return self::safeUrl($url);
    }

    private static function externalLinkAttributes($url)
    {
        return 'href="' . self::h($url) . '" target="_blank" rel="noopener noreferrer"';
    }

    private static function h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private static function assets()
    {
        $customCss = '';
        $config = self::config();
        if ($config && isset($config->customCss)) {
            $customCss = trim((string) $config->customCss);
        }

        $html = '';
        $cssUrl = self::assetUrl('public/mediashelf.css');
        if ($cssUrl !== '') {
            $html .= '<link rel="stylesheet" href="' . self::h($cssUrl) . '">';
        }

        $jsUrl = self::assetUrl('public/mediashelf.js');
        if ($jsUrl !== '') {
            $html .= '<script src="' . self::h($jsUrl) . '" defer></script>';
        }

        if ($customCss !== '') {
            $html .= '<style>' . self::cssText($customCss) . '</style>';
        }

        return $html;
    }

    private static function cssText($value)
    {
        return str_ireplace('</style', '<\/style', (string) $value);
    }

    private static function assetUrl($path)
    {
        try {
            if (class_exists('\Widget\Options') && class_exists('\Typecho\Common')) {
                $options = \Widget\Options::alloc();
                if (!empty($options->pluginUrl)) {
                    return \Typecho\Common::url('MediaShelf/' . ltrim($path, '/'), $options->pluginUrl);
                }

                if (!empty($options->siteUrl)) {
                    return \Typecho\Common::url('usr/plugins/MediaShelf/' . ltrim($path, '/'), $options->siteUrl);
                }
            }
        } catch (\Throwable $e) {
            return '';
        }

        return '';
    }
}
