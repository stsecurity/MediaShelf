<?php

namespace TypechoPlugin\MediaShelf\Lib;

class Admin
{
    public static function requireAdministrator()
    {
        $user = self::globalObject('user');
        if ($user && method_exists($user, 'pass')) {
            $user->pass('administrator');
            return;
        }

        if (class_exists('\Widget\User') && method_exists('\Widget\User', 'alloc')) {
            \Widget\User::alloc()->pass('administrator');
            return;
        }

        if (class_exists('\Widget_User') && method_exists('\Widget_User', 'alloc')) {
            \Widget_User::alloc()->pass('administrator');
            return;
        }

        throw new \RuntimeException('Unable to verify admin permissions.');
    }

    public static function protect()
    {
        $security = self::globalObject('security');
        if ($security && method_exists($security, 'protect')) {
            $security->protect();
            return;
        }

        throw new \RuntimeException('Unable to verify request token.');
    }

    public static function h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function panelUrl($file, array $params = [])
    {
        if (class_exists('\Utils\Helper')) {
            $url = \Utils\Helper::url($file);
        } else {
            $url = 'extending.php?panel=' . rawurlencode($file);
        }

        return self::appendParams($url, $params);
    }

    public static function tokenUrl($url)
    {
        $security = self::globalObject('security');
        if ($security && method_exists($security, 'getTokenUrl')) {
            return $security->getTokenUrl($url);
        }

        return $url;
    }

    public static function redirect($url)
    {
        $response = self::globalObject('response');
        if ($response && method_exists($response, 'redirect')) {
            $response->redirect($url);
            return;
        }

        header('Location: ' . $url);
        exit;
    }

    public static function isPost()
    {
        return isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST';
    }

    public static function post($key, $default = '')
    {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    public static function query($key, $default = '')
    {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    public static function jsonArrayText($json)
    {
        $items = self::decodeJsonArray($json);
        if (!$items) {
            return '';
        }

        return implode(', ', array_map('strval', $items));
    }

    public static function decodeJsonArray($json)
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function appendParams($url, array $params)
    {
        if (!$params) {
            return $url;
        }

        $query = http_build_query($params);
        $separator = strpos($url, '?') === false ? '?' : '&';

        return $url . $separator . $query;
    }

    private static function globalObject($name)
    {
        return isset($GLOBALS[$name]) && is_object($GLOBALS[$name]) ? $GLOBALS[$name] : null;
    }
}
