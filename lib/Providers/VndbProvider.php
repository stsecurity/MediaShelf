<?php

namespace TypechoPlugin\MediaShelf\Lib\Providers;

require_once __DIR__ . '/BaseProvider.php';

class VndbProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'VNDB';
    }

    public function search(string $query, string $category): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $data = $this->vndb([
            'filters' => ['search', '=', $query],
            'fields' => 'id,title,alttitle,image.url,released,developers.name,tags.name,description',
            'results' => 100,
        ]);

        $items = isset($data['results']) && is_array($data['results']) ? $data['results'] : [];
        return array_values(array_map(function ($item) use ($category) {
            return $this->mapVisualNovel($item, $category);
        }, $items));
    }

    public function getDetails(string $id, string $category): array
    {
        $id = trim($id);
        if (!preg_match('/^v[0-9]+$/', $id)) {
            throw new \InvalidArgumentException('Invalid VNDB visual novel id.');
        }

        $data = $this->vndb([
            'filters' => ['id', '=', $id],
            'fields' => 'id,title,alttitle,image.url,released,developers.name,tags.name,description',
            'results' => 1,
        ]);

        $items = isset($data['results']) && is_array($data['results']) ? $data['results'] : [];
        if (!$items) {
            throw new \RuntimeException('VNDB did not return a matching visual novel.');
        }

        return $this->mapVisualNovel($items[0], $category, true);
    }

    private function vndb(array $payload)
    {
        return $this->http->postJson('https://api.vndb.org/kana/vn', $payload, [
            'Accept' => 'application/json',
        ]);
    }

    private function mapVisualNovel(array $item, $category, $details = false)
    {
        $developers = [];
        if (!empty($item['developers']) && is_array($item['developers'])) {
            foreach ($item['developers'] as $developer) {
                $developers[] = isset($developer['name']) ? $developer['name'] : '';
            }
        }

        $tags = [];
        if (!empty($item['tags']) && is_array($item['tags'])) {
            foreach ($item['tags'] as $tag) {
                $tags[] = isset($tag['name']) ? $tag['name'] : '';
            }
        }

        $id = isset($item['id']) ? $item['id'] : '';

        return $this->result([
            'id' => $id,
            'category' => $category,
            'title' => isset($item['title']) ? $item['title'] : '',
            'original_title' => isset($item['alttitle']) ? $item['alttitle'] : '',
            'cover_url' => isset($item['image']['url']) ? $item['image']['url'] : '',
            'release_date' => isset($item['released']) ? $item['released'] : '',
            'creators' => $developers,
            'description' => isset($item['description']) ? $item['description'] : '',
            'tags' => $tags,
            'external_ids' => [
                'vndb' => $id,
                'url' => $id ? 'https://vndb.org/' . $id : '',
            ],
            'source_url' => $id ? 'https://vndb.org/' . $id : '',
            'source_payload' => $details ? $item : null,
        ]);
    }
}
