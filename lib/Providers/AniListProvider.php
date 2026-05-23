<?php

namespace TypechoPlugin\MediaShelf\Lib\Providers;

require_once __DIR__ . '/BaseProvider.php';

class AniListProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'AniList';
    }

    public function search(string $query, string $category): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $data = $this->graphql(
            'query ($search: String, $type: MediaType) {
                Page(page: 1, perPage: 50) {
                    media(search: $search, type: $type, sort: SEARCH_MATCH) {
                        id
                        title { romaji english native }
                        coverImage { large }
                        description(asHtml: false)
                        startDate { year month day }
                        genres
                        tags { name rank }
                        siteUrl
                    }
                }
            }',
            ['search' => $query, 'type' => $this->mediaType($category)]
        );

        $items = isset($data['data']['Page']['media']) && is_array($data['data']['Page']['media']) ? $data['data']['Page']['media'] : [];
        return array_values(array_map(function ($item) use ($category) {
            return $this->mapMedia($item, $category);
        }, $items));
    }

    public function getDetails(string $id, string $category): array
    {
        $data = $this->graphql(
            'query ($id: Int, $type: MediaType) {
                Media(id: $id, type: $type) {
                    id
                    title { romaji english native }
                    coverImage { large extraLarge }
                    description(asHtml: false)
                    startDate { year month day }
                    format
                    status
                    genres
                    tags { name rank }
                    studios { nodes { name } }
                    staff(perPage: 8) { nodes { name { full } } }
                    siteUrl
                }
            }',
            ['id' => (int) $id, 'type' => $this->mediaType($category)]
        );

        $media = isset($data['data']['Media']) && is_array($data['data']['Media']) ? $data['data']['Media'] : [];
        if (!$media) {
            throw new \RuntimeException('AniList did not return a matching work.');
        }

        return $this->mapMedia($media, $category, true);
    }

    private function graphql($query, array $variables)
    {
        $data = $this->http->postJson('https://graphql.anilist.co', [
            'query' => $query,
            'variables' => $variables,
        ]);

        if (!empty($data['errors'])) {
            throw new \RuntimeException('AniList returned an API error.');
        }

        return $data;
    }

    private function mapMedia(array $media, $category, $details = false)
    {
        $title = isset($media['title']) && is_array($media['title']) ? $media['title'] : [];
        $creators = [];
        if (!empty($media['studios']['nodes'])) {
            foreach ($media['studios']['nodes'] as $node) {
                $creators[] = isset($node['name']) ? $node['name'] : '';
            }
        }
        if (!$creators && !empty($media['staff']['nodes'])) {
            foreach ($media['staff']['nodes'] as $node) {
                $creators[] = isset($node['name']['full']) ? $node['name']['full'] : '';
            }
        }

        $tags = isset($media['genres']) && is_array($media['genres']) ? $media['genres'] : [];
        if (!empty($media['tags']) && is_array($media['tags'])) {
            foreach ($media['tags'] as $tag) {
                if (!isset($tag['rank']) || (int) $tag['rank'] >= 60) {
                    $tags[] = isset($tag['name']) ? $tag['name'] : '';
                }
            }
        }

        $cover = '';
        if (!empty($media['coverImage']['extraLarge'])) {
            $cover = $media['coverImage']['extraLarge'];
        } elseif (!empty($media['coverImage']['large'])) {
            $cover = $media['coverImage']['large'];
        }

        return $this->result([
            'id' => isset($media['id']) ? $media['id'] : '',
            'category' => $category,
            'title' => !empty($title['english']) ? $title['english'] : (!empty($title['romaji']) ? $title['romaji'] : (isset($title['native']) ? $title['native'] : '')),
            'original_title' => !empty($title['native']) ? $title['native'] : (!empty($title['romaji']) ? $title['romaji'] : ''),
            'cover_url' => $cover,
            'release_date' => !empty($media['startDate']) && is_array($media['startDate']) ? $this->dateFromParts($media['startDate']) : '',
            'creators' => $creators,
            'description' => isset($media['description']) ? $media['description'] : '',
            'tags' => $tags,
            'external_ids' => [
                'anilist' => isset($media['id']) ? (string) $media['id'] : '',
                'url' => isset($media['siteUrl']) ? $media['siteUrl'] : '',
            ],
            'source_url' => isset($media['siteUrl']) ? $media['siteUrl'] : '',
            'source_payload' => $details ? $media : null,
        ]);
    }

    private function mediaType($category)
    {
        return $category === 'manga' ? 'MANGA' : 'ANIME';
    }
}
