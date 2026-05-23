<?php

namespace TypechoPlugin\MediaShelf;

use Widget\Archive;

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/WorkRepository.php';
require_once __DIR__ . '/lib/Renderer.php';
require_once __DIR__ . '/lib/ContentHooks.php';

class Action extends Archive
{
    public function render()
    {
        Lib\ContentHooks::persist();
        $this->renderThemePage(Lib\Renderer::renderShelf(), $this->pageTitle());
    }

    public function renderDetail()
    {
        Lib\ContentHooks::persist();
        $this->renderThemePage(Lib\Renderer::renderDetail($this->detailSlug()), $this->pageTitle());
    }

    private function renderThemePage($content, $title)
    {
        $this->prepareThemeArchive($title, $content);
        if (method_exists($this, 'setArchiveTitle')) {
            $this->setArchiveTitle($title);
        }
        if (method_exists($this, 'setArchiveType')) {
            $this->setArchiveType('page');
        }
        if (method_exists($this, 'setArchiveSlug')) {
            $this->setArchiveSlug('mediashelf');
        }

        if (!$this->themeFileExists('header.php') || !$this->themeFileExists('footer.php')) {
            echo $content;
            return;
        }

        $this->need('header.php');
        if ($this->themeName() === 'material') {
            $this->openMaterialThemeFrame();
            echo $content;
            $this->closeMaterialThemeFrame();
            if ($this->themeFileExists('sidebar.php')) {
                $this->need('sidebar.php');
            }
        } else {
            echo '<main class="mediashelf-theme-page" id="main" role="main">';
            echo $content;
            echo '</main>';
        }
        $this->need('footer.php');
    }

    private function prepareThemeArchive($title, $content)
    {
        $created = time();
        $slug = 'mediashelf';
        $path = $this->requestPath();

        $this->push([
            'cid' => 0,
            'title' => $title,
            'slug' => $slug,
            'created' => $created,
            'modified' => $created,
            'text' => $content,
            'order' => 0,
            'authorId' => $this->authorId(),
            'template' => '',
            'type' => 'page',
            'status' => 'publish',
            'password' => '',
            'commentsNum' => 0,
            'allowComment' => 0,
            'allowPing' => 0,
            'allowFeed' => 0,
            'parent' => 0,
            'pathinfo' => $path,
            'path' => $path,
            'permalink' => $this->absoluteUrl($path),
        ]);
    }

    private function openMaterialThemeFrame()
    {
        echo '<div class="material-layout mdl-js-layout has-drawer is-upgraded">';
        echo '<main class="material-layout__content" id="main">';
        echo '<div class="min-height-for-footer">';
        echo '<div id="top"></div>';
        echo '<button class="MD-burger-icon sidebar-toggle">';
        echo '<span id="MD-burger-id" class="MD-burger-layer"></span>';
        echo '</button>';
        echo '<div class="mediashelf-theme-page mediashelf-theme-page--material">';
    }

    private function closeMaterialThemeFrame()
    {
        echo '</div>';
        echo '</div>';
        echo '</main>';
    }

    private function pageTitle()
    {
        try {
            if (class_exists('\Utils\Helper')) {
                $config = \Utils\Helper::options()->plugin('MediaShelf');
                if (!empty($config->shelfTitle)) {
                    return (string) $config->shelfTitle;
                }
            }
        } catch (\Throwable $e) {
            return 'Media Shelf';
        }

        return 'Media Shelf';
    }

    private function themeName()
    {
        try {
            return isset($this->options->theme) ? (string) $this->options->theme : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function requestPath()
    {
        try {
            $path = (string) $this->request->getPathInfo();
            return $path !== '' ? $path : '/works';
        } catch (\Throwable $e) {
            return '/works';
        }
    }

    private function detailSlug()
    {
        $slug = (string) $this->request->get('slug', '');
        if ($slug !== '') {
            return $slug;
        }

        $parts = array_values(array_filter(explode('/', trim($this->requestPath(), '/')), function ($part) {
            return $part !== '';
        }));

        return count($parts) >= 2 ? (string) end($parts) : '';
    }

    private function absoluteUrl($path)
    {
        try {
            $siteUrl = rtrim((string) $this->options->siteUrl, '/');
            return $siteUrl . '/' . ltrim((string) $path, '/');
        } catch (\Throwable $e) {
            return (string) $path;
        }
    }

    private function authorId()
    {
        try {
            if (isset($this->user->uid)) {
                return (int) $this->user->uid;
            }
        } catch (\Throwable $e) {
            return 1;
        }

        return 1;
    }

    private function themeFileExists($file)
    {
        try {
            $dir = method_exists($this, 'getThemeDir') ? (string) $this->getThemeDir() : '';
            return $dir !== '' && is_file($dir . $file);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
