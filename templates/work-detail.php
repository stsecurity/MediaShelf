<?php

use TypechoPlugin\MediaShelf\Lib\Renderer;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/WorkRepository.php';
require_once dirname(__DIR__) . '/lib/Renderer.php';

$slug = isset($slug) ? (string) $slug : (isset($_GET['slug']) ? (string) $_GET['slug'] : '');
echo Renderer::renderDetail($slug);
