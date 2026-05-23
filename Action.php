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
        $this->renderThemePage(Lib\Renderer::renderDetail((string) $this->request->get('slug', '')), $this->pageTitle());
    }

    private function renderThemePage($content, $title)
    {
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
