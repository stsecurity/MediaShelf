<?php

namespace TypechoPlugin\MediaShelf\Lib;

class ContentHooks
{
    private const CONTENT_COMPONENT = 'Widget\\Base\\Contents:contentEx';

    public static function callback()
    {
        return ['TypechoPlugin\\MediaShelf\\Plugin', 'contentEx'];
    }

    public static function registerRuntime(array $callback = null)
    {
        if (!class_exists('\\Typecho\\Plugin')) {
            return;
        }

        \Typecho\Plugin::factory('Widget\\Base\\Contents')->contentEx = $callback ?: self::callback();
    }

    public static function persist(array $callback = null)
    {
        if (!class_exists('\\Typecho\\Plugin') || !class_exists('\\Typecho\\Db')) {
            return false;
        }

        $callback = $callback ?: self::callback();
        $plugins = \Typecho\Plugin::export();
        $changed = false;

        if (!isset($plugins['handles']) || !is_array($plugins['handles'])) {
            $plugins['handles'] = [];
        }

        if (!isset($plugins['handles'][self::CONTENT_COMPONENT]) || !is_array($plugins['handles'][self::CONTENT_COMPONENT])) {
            $plugins['handles'][self::CONTENT_COMPONENT] = [];
        }

        if (!self::containsCallback($plugins['handles'][self::CONTENT_COMPONENT], $callback)) {
            $plugins['handles'][self::CONTENT_COMPONENT][self::nextWeight($plugins['handles'][self::CONTENT_COMPONENT])] = $callback;
            ksort($plugins['handles'][self::CONTENT_COMPONENT], SORT_NUMERIC);
            $changed = true;
        }

        if (!isset($plugins['activated']['MediaShelf']) || !is_array($plugins['activated']['MediaShelf'])) {
            return false;
        }

        if (!isset($plugins['activated']['MediaShelf']['handles']) || !is_array($plugins['activated']['MediaShelf']['handles'])) {
            $plugins['activated']['MediaShelf']['handles'] = [];
        }

        if (
            !isset($plugins['activated']['MediaShelf']['handles'][self::CONTENT_COMPONENT])
            || !is_array($plugins['activated']['MediaShelf']['handles'][self::CONTENT_COMPONENT])
        ) {
            $plugins['activated']['MediaShelf']['handles'][self::CONTENT_COMPONENT] = [];
        }

        if (!self::containsCallback($plugins['activated']['MediaShelf']['handles'][self::CONTENT_COMPONENT], $callback)) {
            $plugins['activated']['MediaShelf']['handles'][self::CONTENT_COMPONENT][] = $callback;
            $changed = true;
        }

        if (!$changed) {
            return false;
        }

        $db = \Typecho\Db::get();
        $db->query($db->update('table.options')
            ->rows(['value' => serialize($plugins)])
            ->where('name = ?', 'plugins'));

        return true;
    }

    private static function containsCallback(array $callbacks, array $expected)
    {
        $needle = serialize($expected);
        foreach ($callbacks as $callback) {
            if (serialize($callback) === $needle) {
                return true;
            }
        }

        return false;
    }

    private static function nextWeight(array $callbacks)
    {
        if (!$callbacks) {
            return '0';
        }

        $weights = array_map('floatval', array_keys($callbacks));
        $weight = max($weights);

        return (string) ($weight + 0.001);
    }
}
