<?php

use TypechoPlugin\MediaShelf\Lib\Admin;
use TypechoPlugin\MediaShelf\Lib\ProviderRegistry;
use TypechoPlugin\MediaShelf\Lib\WorkRepository;

if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/Admin.php';
require_once dirname(__DIR__) . '/lib/WorkRepository.php';
require_once dirname(__DIR__) . '/lib/ProviderRegistry.php';

Admin::requireAdministrator();

$repository = new WorkRepository();
$config = mediashelf_import_config();
$registry = new ProviderRegistry($config);
$category = (string) Admin::query('category', 'anime');
if (!isset(WorkRepository::CATEGORIES[$category])) {
    $category = 'anime';
}

$providerKey = (string) Admin::query('provider', '');
if ($providerKey === '') {
    $providerKey = $registry->defaultProviderKey($category);
}

$query = trim((string) Admin::query('q', ''));
$results = [];
$error = '';
$message = (string) Admin::query('message', '');
$resultPage = max(1, (int) Admin::query('page', 1));
$resultsPerPage = 20;

if (Admin::isPost()) {
    try {
        Admin::protect();
        $action = (string) Admin::post('mediashelf_action', '');
        if ($action !== 'import') {
            throw new InvalidArgumentException('Unknown import action.');
        }

        $postCategory = (string) Admin::post('category', 'other');
        $postProvider = (string) Admin::post('provider', '');
        $externalId = (string) Admin::post('external_id', '');

        $provider = $registry->make($postProvider, $postCategory);
        $details = $provider->getDetails($externalId, $postCategory);
        $newId = $repository->createImportedDraft($details);

        Admin::redirect(Admin::panelUrl('MediaShelf/admin/edit.php', ['id' => $newId, 'message' => 'imported']));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (!Admin::isPost() && $query !== '' && $providerKey !== '') {
    try {
        $provider = $registry->make($providerKey, $category);
        $results = $provider->search($query, $category);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$totalResults = count($results);
$totalPages = max(1, (int) ceil($totalResults / $resultsPerPage));
if ($resultPage > $totalPages) {
    $resultPage = $totalPages;
}
$pagedResults = array_slice($results, ($resultPage - 1) * $resultsPerPage, $resultsPerPage);
$editUrl = Admin::panelUrl('MediaShelf/admin/edit.php');
$importUrl = Admin::panelUrl('MediaShelf/admin/import.php');
$importForm = mediashelf_import_form_target($importUrl);
$providerOptions = $registry->providerOptionsForCategory($category);
$statuses = $registry->statuses();

function mediashelf_import_config()
{
    try {
        if (class_exists('\Utils\Helper')) {
            $config = \Utils\Helper::options()->plugin('MediaShelf');
            if (is_object($config) && method_exists($config, 'toArray')) {
                return $config->toArray();
            }

            return is_array($config) ? $config : [];
        }
    } catch (Throwable $e) {
        return [];
    }

    return [];
}

function mediashelf_import_missing(array $status)
{
    return !empty($status['missing']) ? implode(', ', $status['missing']) : '';
}

function mediashelf_import_categories(array $categories)
{
    $labels = [];
    foreach ($categories as $category) {
        $labels[] = isset(WorkRepository::CATEGORIES[$category]) ? WorkRepository::CATEGORIES[$category] : $category;
    }

    return implode(', ', $labels);
}

function mediashelf_import_value(array $result, $key, $default = '')
{
    return isset($result[$key]) && $result[$key] !== null ? $result[$key] : $default;
}

function mediashelf_import_form_target($url)
{
    $parts = parse_url($url);
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $path = isset($parts['path']) ? $parts['path'] : $url;
    if ($path === '') {
        $path = $url;
    }

    return [
        'action' => $path,
        'hidden' => $query,
    ];
}

function mediashelf_import_page_url($page, $category, $provider, $query)
{
    return Admin::panelUrl('MediaShelf/admin/import.php', [
        'category' => $category,
        'provider' => $provider,
        'q' => $query,
        'page' => $page,
    ]);
}

include 'header.php';
include 'menu.php';
?>

<style>
.mediashelf-import-toolbar {
    align-items: flex-end;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
}

.mediashelf-import-toolbar form {
    margin: 0;
}

.mediashelf-import-toolbar .operate {
    align-items: flex-end;
    display: flex;
    flex-wrap: wrap;
    gap: 10px 12px;
    margin: 0;
}

.mediashelf-import-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin: 0;
}

.mediashelf-import-field input,
.mediashelf-import-field select {
    box-sizing: border-box;
    min-height: 32px;
}

.mediashelf-import-actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.mediashelf-import-actions .btn,
.mediashelf-import-result-action .btn {
    align-items: center;
    box-sizing: border-box;
    display: inline-flex;
    justify-content: center;
    line-height: 1.2;
    min-height: 32px;
    vertical-align: middle;
}

.mediashelf-import-result {
    align-items: flex-start;
    display: flex;
    gap: 10px;
}

.mediashelf-import-cover {
    flex: 0 0 auto;
    height: 64px;
    object-fit: cover;
    width: 48px;
}

.mediashelf-import-result-title {
    min-width: 0;
}

.mediashelf-import-result-action {
    margin: 0;
}

.mediashelf-import-pagination {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 16px 0;
}

.mediashelf-import-pagination .btn {
    align-items: center;
    box-sizing: border-box;
    display: inline-flex;
    justify-content: center;
    line-height: 1.2;
    min-height: 32px;
    vertical-align: middle;
}

.mediashelf-import-pagination .current {
    font-weight: 700;
}

.mediashelf-import-message {
    align-items: center;
    box-sizing: border-box;
    display: inline-flex;
    height: 32px !important;
    line-height: 1.2;
    margin: 0 0 16px !important;
    min-height: 32px !important;
    padding: 0 14px !important;
    white-space: nowrap;
    width: auto;
}

.mediashelf-import-message p {
    margin: 0;
}

.mediashelf-import-toolbar .mediashelf-import-message {
    margin: 0 !important;
}
</style>

<div class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>Import Works</h2>
        </div>

        <?php if ($message === 'imported'): ?>
            <div class="message success mediashelf-import-message"><p>Imported work saved as an editable draft.</p></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error mediashelf-import-message">
                <p>Media Shelf import error: <?php echo Admin::h($error); ?></p>
            </div>
        <?php endif; ?>

        <div class="typecho-list-operate clearfix mediashelf-import-toolbar">
            <form method="get" action="<?php echo Admin::h($importForm['action']); ?>">
                <?php foreach ($importForm['hidden'] as $name => $value): ?>
                    <input type="hidden" name="<?php echo Admin::h($name); ?>" value="<?php echo Admin::h($value); ?>" />
                <?php endforeach; ?>
                <p class="operate">
                    <label class="mediashelf-import-field">
                        <span>Category</span>
                        <select name="category" onchange="this.form.provider.value=''; this.form.submit();">
                            <?php foreach (WorkRepository::CATEGORIES as $value => $label): ?>
                                <option value="<?php echo Admin::h($value); ?>" <?php if ($category === $value) echo 'selected'; ?>>
                                    <?php echo Admin::h($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="mediashelf-import-field">
                        <span>Provider</span>
                        <select name="provider">
                            <?php if (!$providerOptions): ?>
                                <option value="">No provider for this category</option>
                            <?php else: ?>
                                <?php foreach ($providerOptions as $key => $status): ?>
                                    <?php
                                    $label = $status['label'];
                                    if (!$status['enabled']) {
                                        $label .= ' (disabled)';
                                    } elseif ($status['missing']) {
                                        $label .= ' (missing options)';
                                    }
                                    ?>
                                    <option value="<?php echo Admin::h($key); ?>" <?php if ($providerKey === $key) echo 'selected'; ?>>
                                        <?php echo Admin::h($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </label>
                    <label class="mediashelf-import-field">
                        <span>Search</span>
                        <input type="text" name="q" value="<?php echo Admin::h($query); ?>" />
                    </label>
                    <span class="mediashelf-import-actions">
                        <button type="submit" class="btn primary">Search Provider</button>
                        <a class="btn" href="<?php echo Admin::h(Admin::panelUrl('MediaShelf/admin/edit.php', ['category' => $category])); ?>">Create Manual Draft</a>
                        <?php if ($query !== '' && !$error): ?>
                            <span class="message notice mediashelf-import-message">
                                <?php echo $totalResults ? 'Select a result to import it as a draft.' : 'No provider results found.'; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </p>
            </form>
        </div>

        <?php if ($totalResults): ?>
            <div class="typecho-table-wrap">
                <table class="typecho-list-table">
                    <thead>
                        <tr>
                            <th>Result</th>
                            <th>Provider</th>
                            <th>Release</th>
                            <th>Creators</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagedResults as $result): ?>
                            <tr>
                                <td>
                                    <div class="mediashelf-import-result">
                                        <?php if (mediashelf_import_value($result, 'cover_url')): ?>
                                            <img class="mediashelf-import-cover" src="<?php echo Admin::h(mediashelf_import_value($result, 'cover_url')); ?>" alt="" />
                                        <?php endif; ?>
                                        <div class="mediashelf-import-result-title">
                                            <strong><?php echo Admin::h(mediashelf_import_value($result, 'title', 'Untitled')); ?></strong>
                                            <?php if (mediashelf_import_value($result, 'original_title')): ?>
                                                <br><small><?php echo Admin::h(mediashelf_import_value($result, 'original_title')); ?></small>
                                            <?php endif; ?>
                                            <?php if (mediashelf_import_value($result, 'source_url')): ?>
                                                <br><small><a href="<?php echo Admin::h(mediashelf_import_value($result, 'source_url')); ?>" target="_blank" rel="noopener noreferrer">Source</a></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo Admin::h(mediashelf_import_value($result, 'provider')); ?></td>
                                <td><?php echo Admin::h(mediashelf_import_value($result, 'release_date', '-')); ?></td>
                                <td><?php echo Admin::h(implode(', ', isset($result['creators']) && is_array($result['creators']) ? $result['creators'] : [])); ?></td>
                                <td>
                                    <form class="mediashelf-import-result-action" method="post" action="<?php echo Admin::h(Admin::tokenUrl($importUrl)); ?>">
                                        <input type="hidden" name="mediashelf_action" value="import" />
                                        <input type="hidden" name="category" value="<?php echo Admin::h($category); ?>" />
                                        <input type="hidden" name="provider" value="<?php echo Admin::h($providerKey); ?>" />
                                        <input type="hidden" name="external_id" value="<?php echo Admin::h(mediashelf_import_value($result, 'id')); ?>" />
                                        <button type="submit" class="btn btn-s primary">Import Draft</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="mediashelf-import-pagination">
                    <?php if ($resultPage > 1): ?>
                        <a class="btn primary" href="<?php echo Admin::h(mediashelf_import_page_url($resultPage - 1, $category, $providerKey, $query)); ?>">Previous</a>
                    <?php endif; ?>
                    <span class="current">Page <?php echo Admin::h($resultPage); ?> of <?php echo Admin::h($totalPages); ?></span>
                    <?php if ($resultPage < $totalPages): ?>
                        <a class="btn primary" href="<?php echo Admin::h(mediashelf_import_page_url($resultPage + 1, $category, $providerKey, $query)); ?>">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="typecho-table-wrap">
            <table class="typecho-list-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Categories</th>
                        <th>Status</th>
                        <th>Required Options</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statuses as $status): ?>
                        <tr>
                            <td><?php echo Admin::h($status['label']); ?></td>
                            <td><?php echo Admin::h(mediashelf_import_categories($status['categories'])); ?></td>
                            <td>
                                <?php if (!$status['enabled']): ?>
                                    Disabled
                                <?php elseif ($status['missing']): ?>
                                    Enabled, missing options
                                <?php else: ?>
                                    Enabled
                                <?php endif; ?>
                            </td>
                            <td><?php echo $status['missing'] ? Admin::h(mediashelf_import_missing($status)) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'footer.php';
?>
