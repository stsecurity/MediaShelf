<?php

namespace TypechoPlugin\MediaShelf\Lib\Providers;

require_once __DIR__ . '/BaseProvider.php';

class IgdbProvider extends BaseProvider
{
    private $token = '';

    public function getName(): string
    {
        return 'IGDB';
    }

    public function search(string $query, string $category): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $body = 'search "' . $this->igdbString($query) . '"; fields id,name,cover.url,first_release_date,genres.name,summary,url,involved_companies.company.name,involved_companies.developer,involved_companies.publisher; limit 100;';
        $games = $this->igdb($body);

        return array_values(array_map(function ($game) use ($category) {
            return $this->mapGame($game, $category);
        }, $games));
    }

    public function getDetails(string $id, string $category): array
    {
        $id = trim($id);
        if (!ctype_digit($id)) {
            throw new \InvalidArgumentException('Invalid IGDB game id.');
        }

        $games = $this->igdb('where id = ' . (int) $id . '; fields id,name,cover.url,first_release_date,genres.name,summary,storyline,url,involved_companies.company.name,involved_companies.developer,involved_companies.publisher; limit 1;');
        if (!$games) {
            throw new \RuntimeException('IGDB did not return a matching game.');
        }

        return $this->mapGame($games[0], $category, true);
    }

    private function igdb($body)
    {
        $data = $this->http->postTextJson('https://api.igdb.com/v4/games', $body, [
            'Client-ID' => $this->requireConfig('igdbClientId', 'IGDB client ID'),
            'Authorization' => 'Bearer ' . $this->accessToken(),
            'Accept' => 'application/json',
            'Content-Type' => 'text/plain',
        ]);

        return is_array($data) ? $data : [];
    }

    private function accessToken()
    {
        if ($this->token !== '') {
            return $this->token;
        }

        $data = $this->http->postFormJson('https://id.twitch.tv/oauth2/token', [
            'client_id' => $this->requireConfig('igdbClientId', 'IGDB client ID'),
            'client_secret' => $this->requireConfig('igdbClientSecret', 'IGDB client secret'),
            'grant_type' => 'client_credentials',
        ]);

        if (empty($data['access_token'])) {
            throw new \RuntimeException('IGDB authentication did not return an access token.');
        }

        $this->token = (string) $data['access_token'];
        return $this->token;
    }

    private function mapGame(array $game, $category, $details = false)
    {
        $tags = [];
        if (!empty($game['genres']) && is_array($game['genres'])) {
            foreach ($game['genres'] as $genre) {
                $tags[] = isset($genre['name']) ? $genre['name'] : '';
            }
        }

        $creators = [];
        if (!empty($game['involved_companies']) && is_array($game['involved_companies'])) {
            foreach ($game['involved_companies'] as $company) {
                if (!empty($company['developer']) || !empty($company['publisher'])) {
                    $creators[] = isset($company['company']['name']) ? $company['company']['name'] : '';
                }
            }
        }

        $cover = isset($game['cover']['url']) ? $game['cover']['url'] : '';
        if ($cover !== '') {
            $cover = str_replace('t_thumb', 't_cover_big', $cover);
        }

        $releaseDate = '';
        if (!empty($game['first_release_date'])) {
            $releaseDate = gmdate('Y-m-d', (int) $game['first_release_date']);
        }

        $id = isset($game['id']) ? (string) $game['id'] : '';
        $description = isset($game['summary']) ? $game['summary'] : '';
        if ($description === '' && !empty($game['storyline'])) {
            $description = $game['storyline'];
        }

        return $this->result([
            'id' => $id,
            'category' => $category,
            'title' => isset($game['name']) ? $game['name'] : '',
            'original_title' => '',
            'cover_url' => $cover,
            'release_date' => $releaseDate,
            'creators' => $creators,
            'description' => $description,
            'tags' => $tags,
            'external_ids' => [
                'igdb' => $id,
                'url' => isset($game['url']) ? $game['url'] : '',
            ],
            'source_url' => isset($game['url']) ? $game['url'] : '',
            'source_payload' => $details ? $game : null,
        ]);
    }

    private function igdbString($value)
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);
    }
}
