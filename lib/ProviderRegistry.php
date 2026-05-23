<?php

namespace TypechoPlugin\MediaShelf\Lib;

use TypechoPlugin\MediaShelf\Lib\Providers\AniListProvider;
use TypechoPlugin\MediaShelf\Lib\Providers\IgdbProvider;
use TypechoPlugin\MediaShelf\Lib\Providers\MusicBrainzProvider;
use TypechoPlugin\MediaShelf\Lib\Providers\OpenLibraryProvider;
use TypechoPlugin\MediaShelf\Lib\Providers\RawgProvider;
use TypechoPlugin\MediaShelf\Lib\Providers\SteamProvider;
use TypechoPlugin\MediaShelf\Lib\Providers\VndbProvider;

require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/ProviderInterface.php';
require_once __DIR__ . '/Providers/AniListProvider.php';
require_once __DIR__ . '/Providers/OpenLibraryProvider.php';
require_once __DIR__ . '/Providers/MusicBrainzProvider.php';
require_once __DIR__ . '/Providers/SteamProvider.php';
require_once __DIR__ . '/Providers/RawgProvider.php';
require_once __DIR__ . '/Providers/IgdbProvider.php';
require_once __DIR__ . '/Providers/VndbProvider.php';

class ProviderRegistry
{
    private $config;
    private $http;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->http = new HttpClient();
    }

    public function definitions()
    {
        return [
            'anilist' => [
                'label' => 'AniList',
                'class' => AniListProvider::class,
                'enabled' => 'enableAniList',
                'categories' => ['anime', 'manga'],
                'required' => [],
            ],
            'openlibrary' => [
                'label' => 'Open Library',
                'class' => OpenLibraryProvider::class,
                'enabled' => 'enableOpenLibrary',
                'categories' => ['novel'],
                'required' => [],
            ],
            'musicbrainz' => [
                'label' => 'MusicBrainz',
                'class' => MusicBrainzProvider::class,
                'enabled' => 'enableMusicBrainz',
                'categories' => ['music'],
                'required' => ['musicBrainzAppName'],
            ],
            'steam' => [
                'label' => 'Steam',
                'class' => SteamProvider::class,
                'enabled' => 'enableSteam',
                'categories' => ['game'],
                'required' => [],
            ],
            'rawg' => [
                'label' => 'RAWG',
                'class' => RawgProvider::class,
                'enabled' => 'enableRawg',
                'categories' => ['game'],
                'required' => ['rawgApiKey'],
            ],
            'igdb' => [
                'label' => 'IGDB',
                'class' => IgdbProvider::class,
                'enabled' => 'enableIgdb',
                'categories' => ['game'],
                'required' => ['igdbClientId', 'igdbClientSecret'],
            ],
            'vndb' => [
                'label' => 'VNDB',
                'class' => VndbProvider::class,
                'enabled' => 'enableVndb',
                'categories' => ['visual_novel'],
                'required' => [],
            ],
        ];
    }

    public function statuses()
    {
        $statuses = [];
        foreach ($this->definitions() as $key => $definition) {
            $missing = $this->missingOptions($definition);
            $statuses[$key] = [
                'key' => $key,
                'label' => $definition['label'],
                'categories' => $definition['categories'],
                'enabled' => $this->enabled($definition['enabled']),
                'missing' => $missing,
            ];
        }

        return $statuses;
    }

    public function providerOptionsForCategory($category)
    {
        $options = [];
        foreach ($this->statuses() as $key => $status) {
            if (in_array($category, $status['categories'], true)) {
                $options[$key] = $status;
            }
        }

        return $options;
    }

    public function defaultProviderKey($category)
    {
        foreach ($this->providerOptionsForCategory($category) as $key => $status) {
            if ($status['enabled'] && !$status['missing']) {
                return $key;
            }
        }

        $options = $this->providerOptionsForCategory($category);
        return $options ? (string) array_key_first($options) : '';
    }

    public function make($key, $category)
    {
        $definitions = $this->definitions();
        if (!isset($definitions[$key])) {
            throw new \InvalidArgumentException('Unknown provider.');
        }

        $definition = $definitions[$key];
        if (!in_array($category, $definition['categories'], true)) {
            throw new \InvalidArgumentException($definition['label'] . ' does not support this category.');
        }

        if (!$this->enabled($definition['enabled'])) {
            throw new \RuntimeException($definition['label'] . ' is disabled in plugin settings.');
        }

        $missing = $this->missingOptions($definition);
        if ($missing) {
            throw new \RuntimeException($definition['label'] . ' is missing required option(s): ' . implode(', ', $missing) . '.');
        }

        $class = $definition['class'];
        return new $class($this->http, $this->config);
    }

    private function enabled($key)
    {
        return !empty($this->config[$key]) && (string) $this->config[$key] === '1';
    }

    private function missingOptions(array $definition)
    {
        $missing = [];
        foreach ($definition['required'] as $key) {
            if (empty($this->config[$key])) {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}
