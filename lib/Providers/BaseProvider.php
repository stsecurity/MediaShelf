<?php

namespace TypechoPlugin\MediaShelf\Lib\Providers;

use TypechoPlugin\MediaShelf\Lib\HttpClient;
use TypechoPlugin\MediaShelf\Lib\ProviderInterface;

require_once __DIR__ . '/../HttpClient.php';
require_once __DIR__ . '/../ProviderInterface.php';

abstract class BaseProvider implements ProviderInterface
{
    protected $http;
    protected $config;

    public function __construct(HttpClient $http, array $config = [])
    {
        $this->http = $http;
        $this->config = $config;
    }

    protected function result(array $data)
    {
        $data['provider'] = $this->getName();
        $data['id'] = isset($data['id']) ? (string) $data['id'] : '';
        $data['title'] = $this->text(isset($data['title']) ? $data['title'] : '');
        $data['original_title'] = $this->text(isset($data['original_title']) ? $data['original_title'] : '');
        $data['cover_url'] = $this->url(isset($data['cover_url']) ? $data['cover_url'] : '');
        $data['release_date'] = $this->safeDate(isset($data['release_date']) ? $data['release_date'] : '');
        $data['creators'] = $this->stringList(isset($data['creators']) ? $data['creators'] : []);
        $data['description'] = $this->longText(isset($data['description']) ? $data['description'] : '');
        $data['tags'] = $this->stringList(isset($data['tags']) ? $data['tags'] : []);
        $data['external_ids'] = isset($data['external_ids']) && is_array($data['external_ids']) ? $data['external_ids'] : [];
        $data['source_url'] = $this->url(isset($data['source_url']) ? $data['source_url'] : '');

        return $data;
    }

    protected function text($value, $max = 255)
    {
        $value = trim(str_replace("\0", '', html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8')));
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }

        return substr($value, 0, $max);
    }

    protected function longText($value)
    {
        return trim(str_replace("\0", '', html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8')));
    }

    protected function stringList($items, $limit = 16)
    {
        if (!is_array($items)) {
            $items = preg_split('/[\r\n,]+/', (string) $items);
        }

        $clean = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $item = isset($item['name']) ? $item['name'] : '';
            }
            $item = $this->text($item, 80);
            if ($item !== '') {
                $clean[] = $item;
            }
        }

        return array_slice(array_values(array_unique($clean)), 0, $limit);
    }

    protected function url($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (strpos($value, '//') === 0) {
            $value = 'https:' . $value;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return '';
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        return in_array(strtolower((string) $scheme), ['http', 'https'], true) ? $value : '';
    }

    protected function safeDate($value)
    {
        $value = $this->text($value, 32);
        return preg_match('/^[0-9A-Za-z ._:\\/-]*$/', $value) ? $value : '';
    }

    protected function dateFromParts(array $parts)
    {
        $year = isset($parts['year']) ? (int) $parts['year'] : 0;
        if ($year <= 0) {
            return '';
        }

        $month = isset($parts['month']) ? (int) $parts['month'] : 0;
        $day = isset($parts['day']) ? (int) $parts['day'] : 0;

        if ($month > 0 && $day > 0) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        if ($month > 0) {
            return sprintf('%04d-%02d', $year, $month);
        }

        return (string) $year;
    }

    protected function requireConfig($key, $label)
    {
        if (empty($this->config[$key])) {
            throw new \RuntimeException($label . ' is required before this provider can import.');
        }

        return (string) $this->config[$key];
    }
}
