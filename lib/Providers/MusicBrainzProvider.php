<?php

namespace TypechoPlugin\MediaShelf\Lib\Providers;

require_once __DIR__ . '/BaseProvider.php';

class MusicBrainzProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'MusicBrainz';
    }

    public function search(string $query, string $category): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $data = $this->http->getJson('https://musicbrainz.org/ws/2/release-group?' . http_build_query([
            'query' => $query,
            'fmt' => 'json',
            'limit' => 100,
        ]), $this->headers());

        $groups = isset($data['release-groups']) && is_array($data['release-groups']) ? $data['release-groups'] : [];
        return array_values(array_map(function ($group) use ($category) {
            return $this->mapReleaseGroup($group, $category);
        }, $groups));
    }

    public function getDetails(string $id, string $category): array
    {
        $id = trim($id);
        if (!preg_match('/^[a-f0-9-]{36}$/i', $id)) {
            throw new \InvalidArgumentException('Invalid MusicBrainz release group MBID.');
        }

        $data = $this->http->getJson('https://musicbrainz.org/ws/2/release-group/' . rawurlencode($id) . '?' . http_build_query([
            'fmt' => 'json',
            'inc' => 'artist-credits+tags',
        ]), $this->headers());

        return $this->mapReleaseGroup($data, $category, true);
    }

    private function mapReleaseGroup(array $group, $category, $details = false)
    {
        $artists = [];
        if (!empty($group['artist-credit']) && is_array($group['artist-credit'])) {
            foreach ($group['artist-credit'] as $credit) {
                if (!empty($credit['artist']['name'])) {
                    $artists[] = $credit['artist']['name'];
                } elseif (!empty($credit['name'])) {
                    $artists[] = $credit['name'];
                }
            }
        }

        $tags = [];
        if (!empty($group['tags']) && is_array($group['tags'])) {
            foreach ($group['tags'] as $tag) {
                $tags[] = isset($tag['name']) ? $tag['name'] : '';
            }
        }
        if (!empty($group['primary-type'])) {
            $tags[] = $group['primary-type'];
        }

        $id = isset($group['id']) ? $group['id'] : '';
        $disambiguation = isset($group['disambiguation']) ? $group['disambiguation'] : '';

        return $this->result([
            'id' => $id,
            'category' => $category,
            'title' => isset($group['title']) ? $group['title'] : '',
            'original_title' => '',
            'cover_url' => '',
            'release_date' => isset($group['first-release-date']) ? $group['first-release-date'] : '',
            'creators' => $artists,
            'description' => $disambiguation,
            'tags' => $tags,
            'external_ids' => [
                'musicbrainz' => $id,
                'url' => $id ? 'https://musicbrainz.org/release-group/' . $id : '',
            ],
            'source_url' => $id ? 'https://musicbrainz.org/release-group/' . $id : '',
            'source_payload' => $details ? $group : null,
        ]);
    }

    private function headers()
    {
        $app = !empty($this->config['musicBrainzAppName']) ? $this->config['musicBrainzAppName'] : 'MediaShelf';
        $contact = !empty($this->config['musicBrainzContact']) ? ' (' . $this->config['musicBrainzContact'] . ')' : '';

        return ['User-Agent' => $this->text($app, 80) . '/0.1' . $this->text($contact, 120)];
    }
}
