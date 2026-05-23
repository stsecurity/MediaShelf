<?php

namespace TypechoPlugin\MediaShelf;

use Typecho\Plugin\Exception;
use Typecho\Plugin\PluginInterface;

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/WorkRepository.php';
require_once __DIR__ . '/lib/ProviderInterface.php';
require_once __DIR__ . '/lib/HttpClient.php';
require_once __DIR__ . '/lib/ProviderRegistry.php';
require_once __DIR__ . '/lib/Admin.php';
require_once __DIR__ . '/lib/Renderer.php';
require_once __DIR__ . '/Action.php';

/**
 * Personal media shelf for anime, manga, games, music, novels, and related works.
 *
 * @package MediaShelf
 * @author MediaShelf
 * @version 0.1.0
 * @link https://github.com/
 */
class Plugin implements PluginInterface
{
    public static function activate()
    {
        try {
            Lib\Database::install();
            self::addPublicRoute();
            self::addAdminPanels();
        } catch (\Throwable $e) {
            throw new Exception('Media Shelf activation failed: ' . $e->getMessage());
        }

        return 'Media Shelf activated. Existing data will be kept when the plugin is disabled.';
    }

    public static function deactivate()
    {
        self::removeAdminPanels();
        self::removePublicRoute();

        return 'Media Shelf disabled. Data was kept.';
    }

    public static function config($form)
    {
        self::addSettingsLayout($form);
        self::addTextInput($form, 'publicSlug', 'works', 'Public page slug', 'Default: works');
        self::addTextInput($form, 'itemsPerPage', '24', 'Items per page', 'Default: 24');
        self::addTextInput($form, 'shelfEyebrow', 'Personal Collection', 'Public page eyebrow', 'Default: Personal Collection');
        self::addTextInput($form, 'shelfTitle', 'Media Shelf', 'Public page title', 'Default: Media Shelf');
        self::addTextInput($form, 'shelfSummary', 'A collection for works I like.', 'Public page summary', 'Default: A collection for works I like.');
        self::addSelectInput($form, 'showTags', ['0' => 'Hide', '1' => 'Show'], '0', 'Show tags', 'Default: Hide.', 'mediashelf-settings-display');
        self::addSelectInput($form, 'showTitle', ['0' => 'Hide', '1' => 'Show'], '1', 'Show title', 'Default: Show.', 'mediashelf-settings-display');
        self::addSelectInput($form, 'showCategory', ['0' => 'Hide', '1' => 'Show'], '1', 'Show category', 'Default: Show.', 'mediashelf-settings-display');
        self::addSelectInput($form, 'showOriginalTitle', ['0' => 'Hide', '1' => 'Show'], '0', 'Show original title', 'Default: Hide.', 'mediashelf-settings-display');
        self::addSelectInput($form, 'showCreators', ['0' => 'Hide', '1' => 'Show'], '0', 'Show creators', 'Default: Hide.', 'mediashelf-settings-display');
        self::addSelectInput($form, 'showLinkedPost', ['0' => 'Hide', '1' => 'Show'], '1', 'Show linked Typecho post', 'Default: Show.', 'mediashelf-settings-display');
        self::addSelectInput($form, 'showReviewText', ['0' => 'Hide', '1' => 'Show'], '1', 'Show review text', 'Default: Show.', 'mediashelf-settings-display');
        self::addSelectInput($form, 'enableAniList', ['0' => 'Disabled', '1' => 'Enabled'], '0', 'Enable AniList import', 'Anime and manga search/import.', 'mediashelf-settings-import mediashelf-settings-import-start');
        self::addSelectInput($form, 'enableOpenLibrary', ['0' => 'Disabled', '1' => 'Enabled'], '0', 'Enable Open Library import', 'Novel/book search/import.', 'mediashelf-settings-import');
        self::addSelectInput($form, 'enableMusicBrainz', ['0' => 'Disabled', '1' => 'Enabled'], '0', 'Enable MusicBrainz import', 'Music search/import. Please keep a meaningful User-Agent.', 'mediashelf-settings-import mediashelf-settings-import-start');
        self::addSelectInput($form, 'enableSteam', ['0' => 'Disabled', '1' => 'Enabled'], '0', 'Enable Steam import', 'Game search/import from Steam AppID, Steam URL, or cached Steam app list.', 'mediashelf-settings-import');
        self::addSelectInput($form, 'enableRawg', ['0' => 'Disabled', '1' => 'Enabled'], '0', 'Enable RAWG import', 'Game search/import. Requires a server-side API key.', 'mediashelf-settings-import mediashelf-settings-import-start');
        self::addSelectInput($form, 'enableIgdb', ['0' => 'Disabled', '1' => 'Enabled'], '0', 'Enable IGDB import', 'Game search/import. Requires server-side Twitch/IGDB credentials.', 'mediashelf-settings-import');
        self::addSelectInput($form, 'enableVndb', ['0' => 'Disabled', '1' => 'Enabled'], '0', 'Enable VNDB import', 'Visual novel search/import.', 'mediashelf-settings-import mediashelf-settings-import-start');
        self::addPasswordInput($form, 'rawgApiKey', '', 'RAWG API key', 'Optional. Stored in Typecho plugin settings, never in source files.', 'mediashelf-settings-clear');
        self::addPasswordInput($form, 'steamApiKey', '', 'Steam Web API key', 'Optional. Used server-side for Steam app-list search when required. Leave blank to keep an existing saved key.');
        self::addTextInput($form, 'igdbClientId', '', 'IGDB client ID', 'Optional server-side provider credential.');
        self::addPasswordInput($form, 'igdbClientSecret', '', 'IGDB client secret', 'Optional. Leave blank to keep an existing saved secret.');
        self::addTextInput($form, 'musicBrainzAppName', 'MediaShelf', 'MusicBrainz app name', 'Used for a meaningful User-Agent when MusicBrainz import is implemented.');
        self::addTextInput($form, 'musicBrainzContact', '', 'MusicBrainz contact', 'Optional email or URL for future MusicBrainz User-Agent.');
        self::addSelectInput($form, 'cacheImportedCovers', ['0' => 'No', '1' => 'Yes'], '0', 'Cache imported covers locally', 'Reserved for a later local cover cache. Default: No.');
        self::addTextareaInput($form, 'customCss', '', 'Custom CSS', 'Optional CSS appended to the public page.');
    }

    public static function personalConfig($form)
    {
    }

    public static function configHandle($settings, $isInit)
    {
        $settings = self::normalizeConfigSettings(is_array($settings) ? $settings : []);

        if (class_exists('\Utils\Helper')) {
            \Utils\Helper::configPlugin('MediaShelf', $settings);
        }

        $slug = isset($settings['publicSlug']) ? (string) $settings['publicSlug'] : null;
        self::addPublicRoute($slug);
    }

    public static function renderWorks(array $filters = [])
    {
        echo Lib\Renderer::renderShelf($filters);
    }

    public static function getWorksHtml(array $filters = [])
    {
        return Lib\Renderer::renderShelf($filters);
    }

    private static function addPublicRoute($slug = null)
    {
        if (!class_exists('\Utils\Helper')) {
            return;
        }

        \Utils\Helper::removeRoute('mediashelf_works');
        \Utils\Helper::removeRoute('mediashelf_work_detail');
        \Utils\Helper::addRoute(
            'mediashelf_works',
            '/' . self::publicSlug($slug),
            'TypechoPlugin\\MediaShelf\\Action',
            'render'
        );
        \Utils\Helper::addRoute(
            'mediashelf_work_detail',
            '/' . self::publicSlug($slug) . '/[slug]',
            'TypechoPlugin\\MediaShelf\\Action',
            'renderDetail'
        );
    }

    private static function removePublicRoute()
    {
        if (class_exists('\Utils\Helper')) {
            \Utils\Helper::removeRoute('mediashelf_works');
            \Utils\Helper::removeRoute('mediashelf_work_detail');
        }
    }

    private static function publicSlug($slug = null)
    {
        $slug = $slug === null ? 'works' : $slug;

        if ($slug === 'works') {
            try {
                if (!class_exists('\Utils\Helper')) {
                    throw new \RuntimeException('Typecho helper is not available.');
                }
                $options = \Utils\Helper::options();
                $config = $options->plugin('MediaShelf');
                if (!empty($config->publicSlug)) {
                    $slug = (string) $config->publicSlug;
                }
            } catch (\Throwable $e) {
                $slug = 'works';
            }
        }

        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_\\-\\/]+/', '-', $slug);
        $slug = trim($slug, '/-_');

        return $slug === '' ? 'works' : $slug;
    }

    private static function addAdminPanels()
    {
        if (!class_exists('\Utils\Helper')) {
            return;
        }

        $menuIndex = \Utils\Helper::addMenu('Media Shelf');
        \Utils\Helper::addPanel(
            $menuIndex,
            'MediaShelf/admin/list.php',
            'Works',
            'Manage Media Shelf works',
            'administrator'
        );
        \Utils\Helper::addPanel(
            $menuIndex,
            'MediaShelf/admin/edit.php',
            'Edit Work',
            'Create or edit a Media Shelf work',
            'administrator',
            true
        );
        \Utils\Helper::addPanel(
            $menuIndex,
            'MediaShelf/admin/import.php',
            'Import',
            'Prepare external imports',
            'administrator'
        );
    }

    private static function removeAdminPanels()
    {
        if (!class_exists('\Utils\Helper')) {
            return;
        }

        $menuIndex = \Utils\Helper::removeMenu('Media Shelf');
        \Utils\Helper::removePanel($menuIndex, 'MediaShelf/admin/list.php');
        \Utils\Helper::removePanel($menuIndex, 'MediaShelf/admin/edit.php');
        \Utils\Helper::removePanel($menuIndex, 'MediaShelf/admin/import.php');
    }

    private static function addSettingsLayout($form)
    {
        if (!class_exists('\Typecho\Widget\Helper\Layout')) {
            return;
        }

        $style = new \Typecho\Widget\Helper\Layout('style');
        $style->html(
            ".mediashelf-settings-display{box-sizing:border-box;float:left;margin-right:2%;width:31%;}" .
            ".mediashelf-settings-import{box-sizing:border-box;float:left;margin-right:2%;width:48%;}" .
            ".mediashelf-settings-import-start,.mediashelf-settings-clear{clear:left;}" .
            ".mediashelf-settings-clear{display:block;}" .
            "@media (max-width: 720px){" .
            ".mediashelf-settings-display,.mediashelf-settings-import{float:none;margin-right:0;width:auto;}" .
            "}"
        );
        $form->addItem($style);
    }

    private static function addTextInput($form, $name, $default, $label, $description, $className = '')
    {
        $class = self::formElementClass('Text');
        if (!$class) {
            return;
        }

        $input = new $class($name, null, $default, $label, $description);
        self::setOptionClass($input, $className);
        $form->addInput($input);
    }

    private static function addTextareaInput($form, $name, $default, $label, $description, $className = '')
    {
        $class = self::formElementClass('Textarea');
        if (!$class) {
            return;
        }

        $input = new $class($name, null, $default, $label, $description);
        self::setOptionClass($input, $className);
        $form->addInput($input);
    }

    private static function addPasswordInput($form, $name, $default, $label, $description, $className = '')
    {
        $class = self::formElementClass('Password');
        if (!$class) {
            self::addTextInput($form, $name, $default, $label, $description, $className);
            return;
        }

        $input = new $class($name, null, $default, $label, $description);
        self::setOptionClass($input, $className);
        $form->addInput($input);
    }

    private static function addSelectInput($form, $name, array $options, $default, $label, $description, $className = '')
    {
        $class = self::formElementClass('Select');
        if (!$class) {
            return;
        }

        $input = new $class($name, $options, $default, $label, $description);
        self::setOptionClass($input, $className);
        $form->addInput($input);
    }

    private static function setOptionClass($input, $className)
    {
        $className = trim((string) $className);
        if ($className === '' || !method_exists($input, 'getAttribute') || !method_exists($input, 'setAttribute')) {
            return;
        }

        $base = (string) $input->getAttribute('class');
        $input->setAttribute('class', trim($base . ' ' . $className));
    }

    private static function normalizeConfigSettings(array $settings)
    {
        $current = self::currentConfig();
        foreach (['rawgApiKey', 'steamApiKey', 'igdbClientSecret'] as $secretKey) {
            if (array_key_exists($secretKey, $settings) && trim((string) $settings[$secretKey]) === '' && !empty($current[$secretKey])) {
                $settings[$secretKey] = $current[$secretKey];
            }
        }

        foreach (['enableAniList', 'enableOpenLibrary', 'enableMusicBrainz', 'enableSteam', 'enableRawg', 'enableIgdb', 'enableVndb', 'cacheImportedCovers'] as $flag) {
            $settings[$flag] = !empty($settings[$flag]) ? '1' : '0';
        }

        foreach (self::displayDefaults() as $flag => $default) {
            $settings[$flag] = array_key_exists($flag, $settings)
                ? (!empty($settings[$flag]) ? '1' : '0')
                : $default;
        }

        return $settings;
    }

    private static function displayDefaults()
    {
        return [
            'showTags' => '0',
            'showTitle' => '1',
            'showCategory' => '1',
            'showOriginalTitle' => '0',
            'showCreators' => '0',
            'showLinkedPost' => '1',
            'showReviewText' => '1',
        ];
    }

    private static function currentConfig()
    {
        try {
            if (!class_exists('\Utils\Helper')) {
                return [];
            }

            $config = \Utils\Helper::options()->plugin('MediaShelf');
            if (is_object($config) && method_exists($config, 'toArray')) {
                return $config->toArray();
            }

            return is_array($config) ? $config : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function formElementClass($name)
    {
        $modern = '\\Typecho\\Widget\\Helper\\Form\\Element\\' . $name;
        if (class_exists($modern)) {
            return $modern;
        }

        $legacy = '\\Typecho_Widget_Helper_Form_Element_' . $name;
        if (class_exists($legacy)) {
            return $legacy;
        }

        return null;
    }
}
