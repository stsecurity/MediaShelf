<?php

namespace TypechoPlugin\MediaShelf\Lib\Providers;

require_once __DIR__ . '/BaseProvider.php';

class SteamProvider extends BaseProvider
{
    const APP_LIST_CACHE_TTL = 604800;
    const APP_LIST_URL = 'https://partner.steam-api.com/IStoreService/GetAppList/v1/';
    const STORE_SEARCH_URL = 'https://store.steampowered.com/search/results/';

    public function getName(): string
    {
        return 'Steam';
    }

    public function search(string $query, string $category): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $appid = $this->appidFromQuery($query);
        if ($appid !== '') {
            return [$this->getDetails($appid, $category)];
        }

        $apps = $this->cachedAppListIfAvailable();
        if (!$apps) {
            return $this->storeSearch($query, $category);
        }

        $needle = $this->lower($query);
        $matches = [];

        foreach ($apps as $app) {
            $name = isset($app['name']) ? (string) $app['name'] : '';
            if ($name === '') {
                continue;
            }

            $haystack = $this->lower($name);
            if (strpos($haystack, $needle) === false) {
                continue;
            }

            $matches[] = $this->result([
                'id' => isset($app['appid']) ? (string) $app['appid'] : '',
                'category' => $category,
                'title' => $name,
                'source_url' => $this->storeUrl(isset($app['appid']) ? (string) $app['appid'] : ''),
                'external_ids' => [
                    'steam_appid' => isset($app['appid']) ? (string) $app['appid'] : '',
                    'url' => $this->storeUrl(isset($app['appid']) ? (string) $app['appid'] : ''),
                ],
                'source_payload' => $app,
            ]);
        }

        return $matches;
    }

    private function cachedAppListIfAvailable()
    {
        try {
            return $this->cachedAppList();
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    private function storeSearch($query, $category)
    {
        $count = 100;
        $matches = [];
        $seen = [];

        $data = $this->http->getJson(self::STORE_SEARCH_URL . '?' . http_build_query([
            'term' => $query,
            'l' => 'english',
            'cc' => 'US',
            'category1' => 998,
            'start' => 0,
            'count' => $count,
            'infinite' => 1,
        ]));

        foreach ($this->parseStoreSearchHtml(isset($data['results_html']) ? (string) $data['results_html'] : '') as $item) {
            $appid = isset($item['id']) ? (string) $item['id'] : '';
            if ($appid === '' || isset($seen[$appid])) {
                continue;
            }

            $seen[$appid] = true;
            $matches[] = $this->result([
                'id' => $appid,
                'category' => $category,
                'title' => isset($item['name']) ? (string) $item['name'] : '',
                'cover_url' => isset($item['cover_url']) ? (string) $item['cover_url'] : '',
                'release_date' => isset($item['release_date']) ? (string) $item['release_date'] : '',
                'source_url' => $this->storeUrl($appid),
                'external_ids' => [
                    'steam_appid' => $appid,
                    'url' => $this->storeUrl($appid),
                ],
                'source_payload' => $item,
            ]);
        }

        return $matches;
    }

    private function parseStoreSearchHtml($html)
    {
        $items = [];
        if ($html === '') {
            return $items;
        }

        if (!preg_match_all('#<a\b[^>]*href="[^"]*/app/([0-9]+)[^"]*"[^>]*>(.*?)</a>#is', $html, $rows, PREG_SET_ORDER)) {
            return $items;
        }

        foreach ($rows as $row) {
            $appid = (string) $row[1];
            $body = $row[2];

            $title = '';
            if (preg_match('#<span[^>]*class="[^"]*\btitle\b[^"]*"[^>]*>(.*?)</span>#is', $body, $matches)) {
                $title = $this->htmlText($matches[1]);
            }

            $cover = '';
            if (preg_match('#<img[^>]+src="([^"]+)"#i', $body, $matches)) {
                $cover = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            }

            $releaseDate = '';
            if (preg_match('#<div[^>]*class="[^"]*\bsearch_released\b[^"]*"[^>]*>(.*?)</div>#is', $body, $matches)) {
                $releaseDate = $this->htmlText($matches[1]);
            }

            $items[] = [
                'id' => $appid,
                'name' => $title,
                'cover_url' => $cover,
                'release_date' => $releaseDate,
            ];
        }

        return $items;
    }

    public function getDetails(string $id, string $category): array
    {
        $appid = $this->appidFromQuery($id);
        if ($appid === '') {
            throw new \InvalidArgumentException('Invalid Steam AppID or Steam URL.');
        }

        $payload = $this->http->getJson('https://store.steampowered.com/api/appdetails?' . http_build_query([
            'appids' => $appid,
            'l' => 'english',
        ]));

        if (empty($payload[$appid]['success']) || empty($payload[$appid]['data']) || !is_array($payload[$appid]['data'])) {
            throw new \RuntimeException('Steam did not return metadata for this AppID.');
        }

        return $this->mapApp($appid, $payload[$appid]['data'], $category);
    }

    private function mapApp($appid, array $app, $category)
    {
        $developers = isset($app['developers']) && is_array($app['developers']) ? $app['developers'] : [];
        $publishers = isset($app['publishers']) && is_array($app['publishers']) ? $app['publishers'] : [];
        $genres = $this->namesFromList(isset($app['genres']) ? $app['genres'] : []);
        $categories = $this->namesFromList(isset($app['categories']) ? $app['categories'] : []);
        $platforms = $this->platforms(isset($app['platforms']) && is_array($app['platforms']) ? $app['platforms'] : []);
        $tags = array_values(array_unique(array_filter(array_merge($genres, $categories, $platforms))));
        $releaseDate = '';

        if (!empty($app['release_date']['date'])) {
            $releaseDate = (string) $app['release_date']['date'];
        }

        return $this->result([
            'id' => (string) $appid,
            'category' => $category,
            'title' => isset($app['name']) ? $app['name'] : '',
            'original_title' => '',
            'cover_url' => isset($app['header_image']) ? $app['header_image'] : '',
            'release_date' => $releaseDate,
            'creators' => array_values(array_unique(array_filter(array_merge($developers, $publishers)))),
            'description' => isset($app['short_description']) ? $app['short_description'] : '',
            'tags' => $tags,
            'external_ids' => [
                'steam_appid' => (string) $appid,
                'url' => $this->storeUrl($appid),
            ],
            'source_url' => $this->storeUrl($appid),
            'source_payload' => $app,
        ]);
    }

    private function cachedAppList()
    {
        $cache = $this->cachePath();
        $cachedApps = [];
        if (is_file($cache)) {
            $decoded = json_decode((string) file_get_contents($cache), true);
            if (is_array($decoded)) {
                $cachedApps = $decoded;
                if (filemtime($cache) >= time() - self::APP_LIST_CACHE_TTL) {
                    return $cachedApps;
                }
            }
        }

        if (empty($this->config['steamApiKey'])) {
            return $cachedApps;
        }

        $apps = [];
        $lastAppid = 0;

        do {
            $query = [
                'include_games' => 1,
                'max_results' => 50000,
            ];

            if ($lastAppid > 0) {
                $query['last_appid'] = $lastAppid;
            }

            if (!empty($this->config['steamApiKey'])) {
                $query['key'] = (string) $this->config['steamApiKey'];
            }

            $data = $this->http->getJson(self::APP_LIST_URL . '?' . http_build_query($query));
            $pageApps = !empty($data['response']['apps']) && is_array($data['response']['apps'])
                ? $data['response']['apps']
                : [];

            foreach ($pageApps as $app) {
                $apps[] = $app;
            }

            $last = $pageApps ? end($pageApps) : [];
            $lastAppid = isset($last['appid']) ? (int) $last['appid'] : 0;
            $hasMore = !empty($data['response']['have_more_results']) && $lastAppid > 0;
        } while ($hasMore);

        if (!$apps) {
            throw new \RuntimeException('Steam app list did not return any apps.');
        }

        $dir = dirname($cache);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents($cache, json_encode($apps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $apps;
    }

    private function cachePath()
    {
        return dirname(dirname(__DIR__)) . '/cache/steam-app-list.json';
    }

    private function appidFromQuery($query)
    {
        $query = trim((string) $query);
        if (ctype_digit($query)) {
            return $query;
        }

        if (preg_match('#store\.steampowered\.com/app/([0-9]+)#i', $query, $matches)) {
            return $matches[1];
        }

        if (preg_match('#steam://store/([0-9]+)#i', $query, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function lower($value)
    {
        $value = (string) $value;
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function htmlText($html)
    {
        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));
    }

    private function storeUrl($appid)
    {
        $appid = trim((string) $appid);
        return ctype_digit($appid) ? 'https://store.steampowered.com/app/' . $appid : '';
    }

    private function namesFromList($items)
    {
        $names = [];
        if (!is_array($items)) {
            return $names;
        }

        foreach ($items as $item) {
            if (is_array($item) && !empty($item['description'])) {
                $names[] = (string) $item['description'];
            }
        }

        return $names;
    }

    private function platforms(array $platforms)
    {
        $labels = [
            'windows' => 'Windows',
            'mac' => 'macOS',
            'linux' => 'Linux',
        ];

        $enabled = [];
        foreach ($labels as $key => $label) {
            if (!empty($platforms[$key])) {
                $enabled[] = $label;
            }
        }

        return $enabled;
    }
}
