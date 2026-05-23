<?php

namespace TypechoPlugin\MediaShelf\Lib\Providers;

require_once __DIR__ . '/BaseProvider.php';

class RawgProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'RAWG';
    }

    public function search(string $query, string $category): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $data = $this->http->getJson('https://api.rawg.io/api/games?' . http_build_query([
            'key' => $this->requireConfig('rawgApiKey', 'RAWG API key'),
            'search' => $query,
            'page_size' => 40,
        ]));

        $games = isset($data['results']) && is_array($data['results']) ? $data['results'] : [];
        return array_values(array_map(function ($game) use ($category) {
            return $this->mapGame($game, $category);
        }, $games));
    }

    public function getDetails(string $id, string $category): array
    {
        $id = trim($id);
        if (!ctype_digit($id)) {
            throw new \InvalidArgumentException('Invalid RAWG game id.');
        }

        $game = $this->http->getJson('https://api.rawg.io/api/games/' . rawurlencode($id) . '?' . http_build_query([
            'key' => $this->requireConfig('rawgApiKey', 'RAWG API key'),
        ]));

        return $this->mapGame($game, $category, true);
    }

    private function mapGame(array $game, $category, $details = false)
    {
        $tags = [];
        foreach (['genres', 'tags'] as $key) {
            if (!empty($game[$key]) && is_array($game[$key])) {
                foreach ($game[$key] as $item) {
                    $tags[] = isset($item['name']) ? $item['name'] : '';
                }
            }
        }

        $creators = [];
        foreach (['developers', 'publishers'] as $key) {
            if (!empty($game[$key]) && is_array($game[$key])) {
                foreach ($game[$key] as $item) {
                    $creators[] = isset($item['name']) ? $item['name'] : '';
                }
            }
        }

        $id = isset($game['id']) ? (string) $game['id'] : '';
        $slug = isset($game['slug']) ? $game['slug'] : '';

        return $this->result([
            'id' => $id,
            'category' => $category,
            'title' => isset($game['name']) ? $game['name'] : '',
            'original_title' => '',
            'cover_url' => isset($game['background_image']) ? $game['background_image'] : '',
            'release_date' => isset($game['released']) ? $game['released'] : '',
            'creators' => $creators,
            'description' => isset($game['description_raw']) ? $game['description_raw'] : (isset($game['description']) ? $game['description'] : ''),
            'tags' => $tags,
            'external_ids' => [
                'rawg' => $id,
                'rawg_slug' => $slug,
                'url' => $slug ? 'https://rawg.io/games/' . $slug : '',
            ],
            'source_url' => $slug ? 'https://rawg.io/games/' . $slug : '',
            'source_payload' => $details ? $game : null,
        ]);
    }
}
