<?php

namespace TypechoPlugin\MediaShelf\Lib;

class HttpClient
{
    private $timeout;
    private $userAgent;

    public function __construct($userAgent = 'MediaShelf/0.1 (+https://typecho.org)', $timeout = 12)
    {
        $this->userAgent = (string) $userAgent;
        $this->timeout = (int) $timeout > 0 ? (int) $timeout : 12;
    }

    public function getJson($url, array $headers = [])
    {
        return $this->decodeJson($this->request('GET', $url, null, $headers), $url);
    }

    public function postJson($url, array $payload, array $headers = [])
    {
        $headers['Content-Type'] = 'application/json';
        return $this->decodeJson($this->request('POST', $url, json_encode($payload), $headers), $url);
    }

    public function postFormJson($url, array $payload, array $headers = [])
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        return $this->decodeJson($this->request('POST', $url, http_build_query($payload), $headers), $url);
    }

    public function postTextJson($url, $body, array $headers = [])
    {
        return $this->decodeJson($this->request('POST', $url, (string) $body, $headers), $url);
    }

    private function request($method, $url, $body = null, array $headers = [])
    {
        $url = $this->assertHttpUrl($url);
        $headers = array_merge(['User-Agent' => $this->userAgent], $headers);

        if (function_exists('curl_init')) {
            return $this->requestWithCurl($method, $url, $body, $headers);
        }

        return $this->requestWithStreams($method, $url, $body, $headers);
    }

    private function requestWithCurl($method, $url, $body, array $headers)
    {
        $handle = curl_init($url);
        if (!$handle) {
            throw new \RuntimeException('Unable to initialize HTTP request.');
        }

        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $this->headerLines($headers));

        if ($method === 'POST') {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body === null ? '' : $body);
        }

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false) {
            throw new \RuntimeException('HTTP request failed: ' . $error);
        }

        if ($status >= 400) {
            throw new \RuntimeException('Provider returned HTTP ' . $status . '.');
        }

        return (string) $response;
    }

    private function requestWithStreams($method, $url, $body, array $headers)
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $this->headerLines($headers)),
                'content' => $body === null ? '' : $body,
                'ignore_errors' => true,
                'timeout' => $this->timeout,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \RuntimeException('HTTP request failed. Check PHP network settings and provider availability.');
        }

        $status = 200;
        if (isset($http_response_header[0]) && preg_match('/\s([0-9]{3})\s/', $http_response_header[0], $matches)) {
            $status = (int) $matches[1];
        }

        if ($status >= 400) {
            throw new \RuntimeException('Provider returned HTTP ' . $status . '.');
        }

        return (string) $response;
    }

    private function decodeJson($response, $url)
    {
        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Provider returned invalid JSON from ' . parse_url($url, PHP_URL_HOST) . '.');
        }

        return $decoded;
    }

    private function headerLines(array $headers)
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return $lines;
    }

    private function assertHttpUrl($url)
    {
        $url = trim((string) $url);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Provider URL must use http or https.');
        }

        return $url;
    }
}
