<?php

namespace TypechoPlugin\MediaShelf;

use Widget\Archive;

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/WorkRepository.php';
require_once __DIR__ . '/lib/Renderer.php';

class Action extends Archive
{
    public function render()
    {
        $this->renderThemePage(Lib\Renderer::renderShelf(), $this->pageTitle());
    }

    public function renderDetail()
    {
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

        if ($this->themeFileExists('page.php')) {
            $this->need('page.php');
            return;
        }

        $this->need('header.php');
        echo '<main class="mediashelf-theme-page" id="main" role="main">';
        echo $content;
        echo '</main>';
        $this->need('footer.php');
    }

    private function prepareThemeArchive($title, $content)
    {
        $created = time();
        $path = $this->requestPath();
        $row = $this->filter([
            'cid' => 0,
            'title' => $title,
            'slug' => 'mediashelf',
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
        ]);

        $row['pathinfo'] = $path;
        $row['path'] = $path;
        $row['url'] = $row['permalink'] = $this->absoluteUrl($path);

        $this->push($row);
        $this->pathinfo = $path;
        $this->path = $path;
        $this->url = $this->permalink = $row['permalink'];
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
