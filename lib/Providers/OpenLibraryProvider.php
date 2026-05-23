<?php

namespace TypechoPlugin\MediaShelf\Lib\Providers;

require_once __DIR__ . '/BaseProvider.php';

class OpenLibraryProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'Open Library';
    }

    public function search(string $query, string $category): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $data = $this->http->getJson('https://openlibrary.org/search.json?' . http_build_query([
            'q' => $query,
            'limit' => 100,
        ]));

        $docs = isset($data['docs']) && is_array($data['docs']) ? $data['docs'] : [];
        return array_values(array_map(function ($doc) use ($category) {
            return $this->mapSearchResult($doc, $category);
        }, $docs));
    }

    public function getDetails(string $id, string $category): array
    {
        $key = '/' . trim($id, '/');
        if (strpos($key, '/works/') !== 0) {
            throw new \InvalidArgumentException('Invalid Open Library work key.');
        }

        $work = $this->http->getJson('https://openlibrary.org' . $key . '.json');
        $authors = $this->authorNames($work);
        $description = '';
        if (isset($work['description'])) {
            $description = is_array($work['description']) && isset($work['description']['value']) ? $work['description']['value'] : $work['description'];
        }

        $cover = '';
        if (!empty($work['covers'][0])) {
            $cover = 'https://covers.openlibrary.org/b/id/' . rawurlencode((string) $work['covers'][0]) . '-L.jpg';
        }

        return $this->result([
            'id' => $key,
            'category' => $category,
            'title' => isset($work['title']) ? $work['title'] : '',
            'original_title' => '',
            'cover_url' => $cover,
            'release_date' => isset($work['first_publish_date']) ? $work['first_publish_date'] : '',
            'creators' => $authors,
            'description' => $description,
            'tags' => isset($work['subjects']) && is_array($work['subjects']) ? array_slice($work['subjects'], 0, 12) : [],
            'external_ids' => [
                'openlibrary' => $key,
                'url' => 'https://openlibrary.org' . $key,
            ],
            'source_url' => 'https://openlibrary.org' . $key,
            'source_payload' => $work,
        ]);
    }

    private function mapSearchResult(array $doc, $category)
    {
        $key = isset($doc['key']) ? $doc['key'] : '';
        $cover = '';
        if (!empty($doc['cover_i'])) {
            $cover = 'https://covers.openlibrary.org/b/id/' . rawurlencode((string) $doc['cover_i']) . '-L.jpg';
        }

        return $this->result([
            'id' => $key,
            'category' => $category,
            'title' => isset($doc['title']) ? $doc['title'] : '',
            'original_title' => isset($doc['title']) ? $doc['title'] : '',
            'cover_url' => $cover,
            'release_date' => isset($doc['first_publish_year']) ? (string) $doc['first_publish_year'] : '',
            'creators' => isset($doc['author_name']) && is_array($doc['author_name']) ? $doc['author_name'] : [],
            'description' => isset($doc['first_sentence'][0]) ? $doc['first_sentence'][0] : '',
            'tags' => [],
            'external_ids' => [
                'openlibrary' => $key,
                'url' => $key ? 'https://openlibrary.org' . $key : '',
            ],
            'source_url' => $key ? 'https://openlibrary.org' . $key : '',
        ]);
    }

    private function authorNames(array $work)
    {
        $names = [];
        if (empty($work['authors']) || !is_array($work['authors'])) {
            return [];
        }

        foreach (array_slice($work['authors'], 0, 5) as $author) {
            if (empty($author['author']['key'])) {
                continue;
            }

            try {
                $data = $this->http->getJson('https://openlibrary.org' . $author['author']['key'] . '.json');
                if (!empty($data['name'])) {
                    $names[] = $data['name'];
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $names;
    }
}
