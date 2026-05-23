<?php

use TypechoPlugin\MediaShelf\Lib\Admin;
use TypechoPlugin\MediaShelf\Lib\ContentHooks;
use TypechoPlugin\MediaShelf\Lib\WorkRepository;

if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/WorkRepository.php';
require_once dirname(__DIR__) . '/lib/Admin.php';
require_once dirname(__DIR__) . '/lib/ContentHooks.php';

Admin::requireAdministrator();
ContentHooks::persist();

$repository = new WorkRepository();
$id = (int) Admin::query('id', 0);
$error = '';
$work = $id > 0 ? $repository->findById($id) : $repository->blankWork();
$message = (string) Admin::query('message', '');
if ($id <= 0) {
    $queryCategory = (string) Admin::query('category', '');
    if (isset(WorkRepository::CATEGORIES[$queryCategory])) {
        $work['category'] = $queryCategory;
    }
}

if (!$work) {
    $error = 'Work not found.';
    $work = $repository->blankWork();
    $id = 0;
}

if (Admin::isPost()) {
    try {
        Admin::protect();
        $id = (int) Admin::post('id', 0);
        $input = [
            'title' => Admin::post('title'),
            'slug' => Admin::post('slug'),
            'category' => Admin::post('category'),
            'original_title' => Admin::post('original_title'),
            'cover_url' => Admin::post('cover_url'),
            'release_date' => Admin::post('release_date'),
            'creators' => Admin::post('creators'),
            'description' => Admin::post('description'),
            'review_text' => Admin::post('review_text'),
            'favorite_level' => Admin::post('favorite_level'),
            'tags' => Admin::post('tags'),
            'blog_cid' => Admin::post('blog_cid'),
            'blog_url' => Admin::post('blog_url'),
            'external_ids_json' => Admin::post('external_ids_json'),
            'status' => Admin::post('status'),
            'sort_order' => Admin::post('sort_order'),
        ];

        if ($id > 0) {
            $repository->update($id, $input);
        } else {
            $id = $repository->create($input);
        }

        Admin::redirect(Admin::panelUrl('MediaShelf/admin/list.php', ['message' => 'saved']));
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $work = mediashelf_edit_work_from_post($id);
    }
}

$isEdit = $id > 0;
$title = $isEdit ? 'Edit Work' : 'Add Work';
$formUrl = Admin::tokenUrl(Admin::panelUrl('MediaShelf/admin/edit.php', $isEdit ? ['id' => $id] : []));
$listUrl = Admin::panelUrl('MediaShelf/admin/list.php');

function mediashelf_edit_work_from_post($id)
{
    return [
        'id' => $id,
        'title' => Admin::post('title'),
        'slug' => Admin::post('slug'),
        'category' => Admin::post('category', 'other'),
        'original_title' => Admin::post('original_title'),
        'cover_url' => Admin::post('cover_url'),
        'release_date' => Admin::post('release_date'),
        'creators_json' => json_encode(mediashelf_edit_list_from_text(Admin::post('creators')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'description' => Admin::post('description'),
        'review_text' => Admin::post('review_text'),
        'favorite_level' => Admin::post('favorite_level', 'normal'),
        'tags_json' => json_encode(mediashelf_edit_list_from_text(Admin::post('tags')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'blog_cid' => Admin::post('blog_cid'),
        'blog_url' => Admin::post('blog_url'),
        'external_ids_json' => Admin::post('external_ids_json'),
        'status' => Admin::post('status', 'draft'),
        'sort_order' => Admin::post('sort_order', 0),
    ];
}

function mediashelf_edit_list_from_text($value)
{
    $items = preg_split('/[\r\n,]+/', (string) $value);
    $items = array_map('trim', $items);
    $items = array_filter($items, function ($item) {
        return $item !== '';
    });

    return array_values(array_unique($items));
}

function mediashelf_edit_field($work, $field, $default = '')
{
    return isset($work[$field]) && $work[$field] !== null ? $work[$field] : $default;
}

function mediashelf_edit_json_object($json)
{
    $json = trim((string) $json);
    if ($json === '') {
        return '{}';
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return $json;
    }

    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

include 'header.php';
include 'menu.php';
?>

<style>
.mediashelf-edit-panel {
    box-sizing: border-box;
    margin: 0 auto;
    max-width: 980px;
    width: min(100%, 980px);
}

.mediashelf-edit-panel form,
.mediashelf-edit-panel .typecho-option {
    width: 100%;
}

.mediashelf-edit-panel .typecho-option input.text,
.mediashelf-edit-panel .typecho-option textarea {
    box-sizing: border-box;
    max-width: none;
    width: 100%;
}

.mediashelf-edit-panel .typecho-option textarea {
    min-height: 120px;
}

.mediashelf-edit-panel .submit {
    align-items: center;
    display: flex;
    gap: 8px;
}

.mediashelf-edit-panel .submit .btn {
    align-items: center;
    box-sizing: border-box;
    display: inline-flex;
    justify-content: center;
    line-height: 1.2;
    min-height: 32px;
}

.mediashelf-edit-message {
    align-items: center;
    box-sizing: border-box;
    display: inline-flex;
    line-height: 1.2;
    margin: 0 0 16px !important;
    min-height: 32px;
    padding: 0 14px !important;
    width: auto;
}

.mediashelf-edit-message p {
    margin: 0;
}
</style>

<div class="main">
    <div class="body container">
        <div class="mediashelf-edit-panel">
            <div class="typecho-page-title">
                <h2><?php echo Admin::h($title); ?></h2>
            </div>

            <?php if ($error): ?>
                <div class="message error mediashelf-edit-message">
                    <p><?php echo Admin::h($error); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($message === 'imported'): ?>
                <div class="message success mediashelf-edit-message">
                    <p>Imported work saved as a draft. Review and edit it before publishing.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo Admin::h($formUrl); ?>">
                <input type="hidden" name="id" value="<?php echo Admin::h($id); ?>" />

                <ul class="typecho-option">
                <li>
                    <label class="typecho-label" for="title">Title</label>
                    <input id="title" name="title" type="text" class="text" required maxlength="255" value="<?php echo Admin::h(mediashelf_edit_field($work, 'title')); ?>" />
                </li>
                <li>
                    <label class="typecho-label" for="slug">Slug</label>
                    <input id="slug" name="slug" type="text" class="text" maxlength="200" value="<?php echo Admin::h(mediashelf_edit_field($work, 'slug')); ?>" />
                    <p class="description">Leave blank to generate one from the title.</p>
                </li>
                <li>
                    <label class="typecho-label" for="category">Category</label>
                    <select id="category" name="category">
                        <?php foreach (WorkRepository::CATEGORIES as $value => $label): ?>
                            <option value="<?php echo Admin::h($value); ?>" <?php if (mediashelf_edit_field($work, 'category', 'other') === $value) echo 'selected'; ?>>
                                <?php echo Admin::h($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </li>
                <li>
                    <label class="typecho-label" for="original_title">Original Title</label>
                    <input id="original_title" name="original_title" type="text" class="text" maxlength="255" value="<?php echo Admin::h(mediashelf_edit_field($work, 'original_title')); ?>" />
                </li>
                <li>
                    <label class="typecho-label" for="cover_url">Cover URL</label>
                    <input id="cover_url" name="cover_url" type="url" class="text" value="<?php echo Admin::h(mediashelf_edit_field($work, 'cover_url')); ?>" />
                </li>
                <li>
                    <label class="typecho-label" for="release_date">Release Date</label>
                    <input id="release_date" name="release_date" type="text" class="text" maxlength="32" value="<?php echo Admin::h(mediashelf_edit_field($work, 'release_date')); ?>" />
                </li>
                <li>
                    <label class="typecho-label" for="creators">Creators</label>
                    <textarea id="creators" name="creators" rows="3"><?php echo Admin::h(Admin::jsonArrayText(mediashelf_edit_field($work, 'creators_json'))); ?></textarea>
                    <p class="description">Separate names with commas or new lines.</p>
                </li>
                <li>
                    <label class="typecho-label" for="description">Description</label>
                    <textarea id="description" name="description" rows="5"><?php echo Admin::h(mediashelf_edit_field($work, 'description')); ?></textarea>
                </li>
                <li>
                    <label class="typecho-label" for="review_text">Review Text</label>
                    <textarea id="review_text" name="review_text" rows="6"><?php echo Admin::h(mediashelf_edit_field($work, 'review_text')); ?></textarea>
                </li>
                <li>
                    <label class="typecho-label" for="favorite_level">Favorite Level</label>
                    <select id="favorite_level" name="favorite_level">
                        <?php foreach (WorkRepository::FAVORITE_LEVELS as $value => $label): ?>
                            <option value="<?php echo Admin::h($value); ?>" <?php if (mediashelf_edit_field($work, 'favorite_level', 'normal') === $value) echo 'selected'; ?>>
                                <?php echo Admin::h($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </li>
                <li>
                    <label class="typecho-label" for="tags">Tags</label>
                    <textarea id="tags" name="tags" rows="3"><?php echo Admin::h(Admin::jsonArrayText(mediashelf_edit_field($work, 'tags_json'))); ?></textarea>
                    <p class="description">Separate tags with commas or new lines.</p>
                </li>
                <li>
                    <label class="typecho-label" for="blog_cid">Related Typecho Post ID</label>
                    <input id="blog_cid" name="blog_cid" type="number" min="1" class="text" value="<?php echo Admin::h(mediashelf_edit_field($work, 'blog_cid')); ?>" />
                </li>
                <li>
                    <label class="typecho-label" for="blog_url">Manual Blog URL</label>
                    <input id="blog_url" name="blog_url" type="url" class="text" value="<?php echo Admin::h(mediashelf_edit_field($work, 'blog_url')); ?>" />
                </li>
                <li>
                    <label class="typecho-label" for="external_ids_json">External IDs JSON</label>
                    <textarea id="external_ids_json" name="external_ids_json" rows="4"><?php echo Admin::h(mediashelf_edit_json_object(mediashelf_edit_field($work, 'external_ids_json', '{}'))); ?></textarea>
                    <p class="description">Optional. Use a JSON object, for example {"anilist":"123"}.</p>
                </li>
                <li>
                    <label class="typecho-label" for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach (WorkRepository::STATUSES as $value => $label): ?>
                            <option value="<?php echo Admin::h($value); ?>" <?php if (mediashelf_edit_field($work, 'status', 'draft') === $value) echo 'selected'; ?>>
                                <?php echo Admin::h($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </li>
                <li>
                    <label class="typecho-label" for="sort_order">Sort Order</label>
                    <input id="sort_order" name="sort_order" type="number" class="text" value="<?php echo Admin::h(mediashelf_edit_field($work, 'sort_order', 0)); ?>" />
                </li>
                </ul>

                <p class="submit">
                    <button type="submit" class="btn primary">Save Work</button>
                    <a class="btn" href="<?php echo Admin::h($listUrl); ?>">Cancel</a>
                </p>
            </form>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'footer.php';
?>
