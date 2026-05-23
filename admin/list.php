<?php

use TypechoPlugin\MediaShelf\Lib\WorkRepository;
use TypechoPlugin\MediaShelf\Lib\Admin;
use TypechoPlugin\MediaShelf\Lib\ContentHooks;

if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/WorkRepository.php';
require_once dirname(__DIR__) . '/lib/Admin.php';
require_once dirname(__DIR__) . '/lib/ContentHooks.php';

Admin::requireAdministrator();
ContentHooks::persist();

$filters = [
    'search' => trim((string) Admin::query('search', '')),
    'category' => (string) Admin::query('category', ''),
    'status' => (string) Admin::query('status', ''),
    'sort' => (string) Admin::query('sort', 'updated'),
];

$works = [];
$error = '';
$message = (string) Admin::query('message', '');
$messageText = '';
$repository = new WorkRepository();

if ($message === 'saved') {
    $messageText = 'Work saved.';
} elseif ($message === 'deleted') {
    $messageText = 'Work deleted.';
} elseif ($message === 'status') {
    $messageText = 'Status updated.';
} elseif ($message === 'sorted') {
    $messageText = 'Sort order saved.';
}

if (Admin::isPost()) {
    try {
        Admin::protect();
        $action = (string) Admin::post('mediashelf_action', '');
        if ($action === 'delete') {
            $repository->delete((int) Admin::post('id', 0));
            Admin::redirect(Admin::panelUrl('MediaShelf/admin/list.php', ['message' => 'deleted']));
        } elseif ($action === 'status') {
            $repository->updateStatus((int) Admin::post('id', 0), (string) Admin::post('status', 'draft'));
            Admin::redirect(Admin::panelUrl('MediaShelf/admin/list.php', ['message' => 'status']));
        } elseif ($action === 'sort') {
            $repository->updateSortOrders(mediashelf_list_ids_from_order(Admin::post('order', '')));
            Admin::redirect(Admin::panelUrl('MediaShelf/admin/list.php', [
                'sort' => 'sort_order',
                'message' => 'sorted',
            ]));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

try {
    $works = $repository->findForAdmin($filters);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$listUrl = Admin::panelUrl('MediaShelf/admin/list.php');
$editUrl = Admin::panelUrl('MediaShelf/admin/edit.php');
$listForm = mediashelf_list_form_target($listUrl);

function mediashelf_list_form_target($url)
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

function mediashelf_list_ids_from_order($order)
{
    $parts = preg_split('/[\s,]+/', (string) $order);
    $ids = [];

    foreach ($parts as $part) {
        $id = (int) $part;
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return $ids;
}

include 'header.php';
include 'menu.php';
?>

<style>
.mediashelf-list-actions {
    align-items: flex-end;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
}

.mediashelf-list-actions .btn {
    align-items: center;
    box-sizing: border-box;
    display: inline-flex;
    justify-content: center;
    line-height: 1.2;
    min-height: 32px;
    vertical-align: middle;
}

.mediashelf-list-filter .operate {
    align-items: flex-end;
    display: flex;
    flex-wrap: wrap;
    gap: 10px 12px;
    margin: 0;
}

.mediashelf-list-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin: 0;
}

.mediashelf-list-field input,
.mediashelf-list-field select,
.mediashelf-status-form select {
    box-sizing: border-box;
    min-height: 32px;
}

.mediashelf-list-search input {
    min-width: 260px;
}

.mediashelf-work-title {
    max-width: 280px;
    min-width: 220px;
    overflow-wrap: normal;
    word-break: normal;
}

.mediashelf-work-title strong {
    overflow-wrap: normal;
    word-break: normal;
}

.mediashelf-work-title small {
    overflow-wrap: anywhere;
}

.mediashelf-status-form,
.mediashelf-delete-form,
.mediashelf-row-actions {
    align-items: center;
    display: flex;
    gap: 8px;
    margin: 0;
}

.mediashelf-status-form select {
    min-width: 130px;
}

.mediashelf-delete-form .btn,
.mediashelf-row-actions .btn {
    align-items: center;
    box-sizing: border-box;
    display: inline-flex;
    justify-content: center;
    min-height: 32px;
}

.mediashelf-row-actions .primary {
    background: #467b96;
    border-color: #467b96;
    color: #fff;
}

.mediashelf-row-actions .btn-warn {
    background: #b94a48;
    border-color: #b94a48;
    color: #fff;
}

.mediashelf-drag-handle {
    color: #999;
    cursor: grab;
    display: inline-flex;
    font-size: 18px;
    line-height: 1;
    margin-right: 8px;
    user-select: none;
    vertical-align: middle;
}

.mediashelf-drag-handle:active {
    cursor: grabbing;
}

.mediashelf-sort-row.is-dragging {
    opacity: .5;
}

.mediashelf-sort-row.drag-over {
    outline: 2px solid #467b96;
    outline-offset: -2px;
}

.mediashelf-list-message {
    align-items: center;
    box-sizing: border-box;
    display: inline-flex;
    flex: 0 0 auto;
    height: 32px !important;
    line-height: 1.2;
    margin: 0 !important;
    min-height: 32px !important;
    padding: 0 14px !important;
    vertical-align: middle;
    width: auto;
}
</style>

<div class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>Media Shelf Works</h2>
        </div>

        <div class="mediashelf-list-actions">
            <a class="btn primary" href="<?php echo Admin::h($editUrl); ?>">Add Work</a>

            <form class="mediashelf-list-filter" method="get" action="<?php echo Admin::h($listForm['action']); ?>">
                <?php foreach ($listForm['hidden'] as $name => $value): ?>
                    <input type="hidden" name="<?php echo Admin::h($name); ?>" value="<?php echo Admin::h($value); ?>" />
                <?php endforeach; ?>
                <p class="operate">
                    <label class="mediashelf-list-field mediashelf-list-search">
                        <span>Search</span>
                        <input type="text" name="search" value="<?php echo Admin::h($filters['search']); ?>" />
                    </label>
                    <label class="mediashelf-list-field">
                        <span>Category</span>
                        <select name="category">
                            <option value="">All categories</option>
                            <?php foreach (WorkRepository::CATEGORIES as $value => $label): ?>
                                <option value="<?php echo Admin::h($value); ?>" <?php if ($filters['category'] === $value) echo 'selected'; ?>>
                                    <?php echo Admin::h($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="mediashelf-list-field">
                        <span>Status</span>
                        <select name="status">
                            <option value="">All statuses</option>
                            <?php foreach (WorkRepository::STATUSES as $value => $label): ?>
                                <option value="<?php echo Admin::h($value); ?>" <?php if ($filters['status'] === $value) echo 'selected'; ?>>
                                    <?php echo Admin::h($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="mediashelf-list-field">
                        <span>Sort</span>
                        <select name="sort">
                            <?php foreach (['updated' => 'Updated date', 'title' => 'Title', 'category' => 'Category', 'sort_order' => 'Manual order'] as $value => $label): ?>
                                <option value="<?php echo Admin::h($value); ?>" <?php if ($filters['sort'] === $value) echo 'selected'; ?>>
                                    <?php echo Admin::h($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn primary">Filter</button>
                </p>
            </form>

            <?php if ($messageText !== ''): ?>
                <div class="message success mediashelf-list-message"><?php echo Admin::h($messageText); ?></div>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="message error">
                <p>Media Shelf error: <?php echo Admin::h($error); ?></p>
            </div>
        <?php endif; ?>

        <div class="typecho-table-wrap">
            <table class="typecho-list-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Sort Order</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$works): ?>
                        <tr>
                            <td colspan="6">No works found yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($works as $work): ?>
                            <tr class="mediashelf-sort-row" draggable="true" data-work-id="<?php echo Admin::h((int) $work['id']); ?>">
                                <td class="mediashelf-work-title">
                                    <span class="mediashelf-drag-handle" title="Drag to sort" aria-hidden="true">::</span>
                                    <strong><?php echo Admin::h(isset($work['title']) ? $work['title'] : 'Untitled'); ?></strong>
                                    <?php if (!empty($work['slug'])): ?>
                                        <br><small><?php echo Admin::h($work['slug']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo Admin::h(isset(WorkRepository::CATEGORIES[$work['category']]) ? WorkRepository::CATEGORIES[$work['category']] : $work['category']); ?></td>
                                <td>
                                    <form class="mediashelf-status-form" method="post" action="<?php echo Admin::h(Admin::tokenUrl($listUrl)); ?>">
                                        <input type="hidden" name="mediashelf_action" value="status" />
                                        <input type="hidden" name="id" value="<?php echo Admin::h((int) $work['id']); ?>" />
                                        <select name="status" aria-label="Status for <?php echo Admin::h(isset($work['title']) ? $work['title'] : 'Untitled'); ?>" onchange="this.form.submit();">
                                            <?php foreach (WorkRepository::STATUSES as $statusValue => $statusLabel): ?>
                                                <option value="<?php echo Admin::h($statusValue); ?>" <?php if ($work['status'] === $statusValue) echo 'selected'; ?>>
                                                    <?php echo Admin::h($statusLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td><?php echo Admin::h(isset($work['sort_order']) ? $work['sort_order'] : 0); ?></td>
                                <td>
                                    <?php
                                    $updatedAt = isset($work['updated_at']) ? (int) $work['updated_at'] : 0;
                                    echo $updatedAt > 0 ? Admin::h(date('Y-m-d H:i', $updatedAt)) : '-';
                                    ?>
                                </td>
                                <td>
                                    <div class="mediashelf-row-actions">
                                        <a class="btn btn-s primary" href="<?php echo Admin::h(Admin::panelUrl('MediaShelf/admin/edit.php', ['id' => (int) $work['id']])); ?>">Edit</a>
                                        <form class="mediashelf-delete-form" method="post" action="<?php echo Admin::h(Admin::tokenUrl($listUrl)); ?>" onsubmit="return confirm('Delete this work?');">
                                            <input type="hidden" name="mediashelf_action" value="delete" />
                                            <input type="hidden" name="id" value="<?php echo Admin::h((int) $work['id']); ?>" />
                                            <button type="submit" class="btn btn-s btn-warn">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($works): ?>
            <form id="mediashelf-sort-form" method="post" action="<?php echo Admin::h(Admin::tokenUrl($listUrl)); ?>">
                <input type="hidden" name="mediashelf_action" value="sort" />
                <input type="hidden" name="order" id="mediashelf-sort-order" value="" />
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var tbody = document.querySelector('.typecho-list-table tbody');
    var sortForm = document.getElementById('mediashelf-sort-form');
    var sortOrder = document.getElementById('mediashelf-sort-order');
    var draggingRow = null;

    if (!tbody || !sortForm || !sortOrder) {
        return;
    }

    function rows() {
        return Array.prototype.slice.call(tbody.querySelectorAll('.mediashelf-sort-row'));
    }

    function saveOrder() {
        sortOrder.value = rows().map(function (row) {
            return row.getAttribute('data-work-id');
        }).join(',');
        sortForm.submit();
    }

    rows().forEach(function (row) {
        row.addEventListener('dragstart', function (event) {
            draggingRow = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.getAttribute('data-work-id'));
        });

        row.addEventListener('dragend', function () {
            row.classList.remove('is-dragging');
            rows().forEach(function (item) {
                item.classList.remove('drag-over');
            });
            draggingRow = null;
        });

        row.addEventListener('dragover', function (event) {
            if (!draggingRow || draggingRow === row) {
                return;
            }

            event.preventDefault();
            row.classList.add('drag-over');
        });

        row.addEventListener('dragleave', function () {
            row.classList.remove('drag-over');
        });

        row.addEventListener('drop', function (event) {
            if (!draggingRow || draggingRow === row) {
                return;
            }

            event.preventDefault();
            row.classList.remove('drag-over');

            var rect = row.getBoundingClientRect();
            var after = event.clientY > rect.top + rect.height / 2;
            tbody.insertBefore(draggingRow, after ? row.nextSibling : row);
            saveOrder();
        });
    });
})();
</script>

<?php
include 'copyright.php';
include 'common-js.php';
include 'footer.php';
?>
