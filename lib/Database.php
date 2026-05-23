<?php

namespace TypechoPlugin\MediaShelf\Lib;

class Database
{
    public static function install()
    {
        $db = self::db();
        $table = self::tableName();

        if (self::isSqlite($db)) {
            $sql = self::sqliteCreateSql($table);
        } else {
            $sql = self::mysqlCreateSql($table);
        }

        $db->query($sql);
    }

    public static function tableName()
    {
        $db = self::db();
        $prefix = method_exists($db, 'getPrefix') ? $db->getPrefix() : '';

        return self::safeIdentifier($prefix . 'mediashelf_works');
    }

    public static function db()
    {
        if (class_exists('\Typecho\Db')) {
            return \Typecho\Db::get();
        }

        if (class_exists('\Typecho_Db')) {
            return \Typecho_Db::get();
        }

        throw new \RuntimeException('Typecho database class was not found.');
    }

    public static function isSqlite($db)
    {
        $adapter = '';

        if (method_exists($db, 'getAdapterName')) {
            $adapter = (string) $db->getAdapterName();
        } elseif (property_exists($db, 'adapterName')) {
            $adapter = (string) $db->adapterName;
        }

        return stripos($adapter, 'sqlite') !== false;
    }

    private static function mysqlCreateSql($table)
    {
        $table = self::quoteIdentifier($table, '`');

        return "CREATE TABLE IF NOT EXISTS {$table} (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `slug` varchar(200) NOT NULL,
            `category` varchar(32) NOT NULL DEFAULT 'other',
            `title` varchar(255) NOT NULL,
            `original_title` varchar(255) DEFAULT NULL,
            `cover_url` text,
            `release_date` varchar(32) DEFAULT NULL,
            `creators_json` text,
            `description` text,
            `review_text` text,
            `blog_cid` int unsigned DEFAULT NULL,
            `blog_url` text,
            `blog_preview_json` text,
            `external_ids_json` text,
            `source_payload_json` text,
            `favorite_level` varchar(32) DEFAULT NULL,
            `tags_json` text,
            `sort_order` int NOT NULL DEFAULT 0,
            `status` varchar(20) NOT NULL DEFAULT 'draft',
            `created_at` int unsigned NOT NULL DEFAULT 0,
            `updated_at` int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_mediashelf_slug` (`slug`),
            KEY `idx_mediashelf_category_sort` (`category`, `sort_order`),
            KEY `idx_mediashelf_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }

    private static function sqliteCreateSql($table)
    {
        $table = self::quoteIdentifier($table, '"');

        return "CREATE TABLE IF NOT EXISTS {$table} (
            \"id\" INTEGER PRIMARY KEY AUTOINCREMENT,
            \"slug\" varchar(200) NOT NULL UNIQUE,
            \"category\" varchar(32) NOT NULL DEFAULT 'other',
            \"title\" varchar(255) NOT NULL,
            \"original_title\" varchar(255) DEFAULT NULL,
            \"cover_url\" text DEFAULT NULL,
            \"release_date\" varchar(32) DEFAULT NULL,
            \"creators_json\" text DEFAULT NULL,
            \"description\" text DEFAULT NULL,
            \"review_text\" text DEFAULT NULL,
            \"blog_cid\" integer DEFAULT NULL,
            \"blog_url\" text DEFAULT NULL,
            \"blog_preview_json\" text DEFAULT NULL,
            \"external_ids_json\" text DEFAULT NULL,
            \"source_payload_json\" text DEFAULT NULL,
            \"favorite_level\" varchar(32) DEFAULT NULL,
            \"tags_json\" text DEFAULT NULL,
            \"sort_order\" integer NOT NULL DEFAULT 0,
            \"status\" varchar(20) NOT NULL DEFAULT 'draft',
            \"created_at\" integer NOT NULL DEFAULT 0,
            \"updated_at\" integer NOT NULL DEFAULT 0
        )";
    }

    private static function quoteIdentifier($identifier, $quote)
    {
        return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
    }

    private static function safeIdentifier($identifier)
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $identifier);
    }
}
