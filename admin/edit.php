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
$relatedFilters = [
    'type' => (string) Admin::query('post_type', 'post'),
    'category' => (int) Admin::query('post_category', 0),
    'published' => (string) Admin::query('post_published', 'all'),
    'search' => trim((string) Admin::query('post_search', '')),
];
$selectedPostCid = (int) mediashelf_edit_field($work, 'blog_cid', 0);
if ((string) Admin::query('mediashelf_related_ajax', '') === '1') {
    $selectedPostCid = (int) Admin::query('selected_cid', $selectedPostCid);
}
$relatedPosts = $repository->relatedPostOptions($relatedFilters, $selectedPostCid);
$relatedCategories = $repository->relatedPostCategories();
$relatedPublishedOptions = $repository->relatedPublishedOptions();
$selectedPostPreview = $repository->blogPreview($selectedPostCid);
$relatedFilterBaseUrl = Admin::panelUrl('MediaShelf/admin/edit.php', $isEdit ? ['id' => $id] : []);

if ((string) Admin::query('mediashelf_related_ajax', '') === '1') {
    mediashelf_edit_json_response([
        'posts' => $relatedPosts,
        'selected_cid' => $selectedPostCid,
    ]);
}

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

function mediashelf_edit_json_response(array $data)
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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

.mediashelf-related-filters {
    align-items: end;
    display: grid;
    gap: 10px;
    grid-template-columns: 130px 170px 150px minmax(170px, 1fr) auto;
    margin: 0 0 10px;
}

.mediashelf-related-filters label {
    display: grid;
    gap: 5px;
}

.mediashelf-related-filters input,
.mediashelf-related-filters select,
.mediashelf-related-select {
    box-sizing: border-box;
    max-width: none;
    width: 100%;
}

.mediashelf-related-preview {
    border: 1px solid #d9d9d6;
    box-sizing: border-box;
    margin-top: 10px;
    padding: 12px;
}

.mediashelf-related-preview.is-empty {
    color: #999;
}

.mediashelf-related-preview-title {
    font-weight: 600;
    margin: 0 0 6px;
}

.mediashelf-related-preview-meta,
.mediashelf-related-preview-excerpt {
    color: #777;
    margin: 0 0 6px;
}

@media (max-width: 760px) {
    .mediashelf-related-filters {
        grid-template-columns: 1fr;
    }
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
                    <label class="typecho-label" for="blog_cid">Related Typecho Post</label>
                    <div class="mediashelf-related-filters" data-filter-base="<?php echo Admin::h($relatedFilterBaseUrl); ?>">
                        <label>
                            Post Type
                            <select id="post_type_filter">
                                <option value="post" <?php if ($relatedFilters['type'] === 'post') echo 'selected'; ?>>Posts</option>
                                <option value="page" <?php if ($relatedFilters['type'] === 'page') echo 'selected'; ?>>Pages</option>
                                <option value="all" <?php if ($relatedFilters['type'] === 'all') echo 'selected'; ?>>Posts and pages</option>
                            </select>
                        </label>
                        <label>
                            Category
                            <select id="post_category_filter">
                                <option value="0">All categories</option>
                                <?php foreach ($relatedCategories as $category): ?>
                                    <option value="<?php echo Admin::h($category['mid']); ?>" <?php if ((int) $relatedFilters['category'] === (int) $category['mid']) echo 'selected'; ?>>
                                        <?php echo Admin::h($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Published Time
                            <select id="post_published_filter">
                                <?php foreach ($relatedPublishedOptions as $value => $label): ?>
                                    <option value="<?php echo Admin::h($value); ?>" <?php if ($relatedFilters['published'] === $value) echo 'selected'; ?>>
                                        <?php echo Admin::h($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Search title
                            <input id="post_search_filter" type="search" class="text" value="<?php echo Admin::h($relatedFilters['search']); ?>" />
                        </label>
                        <button type="button" class="btn" id="mediashelf_post_filter_reset">Reset</button>
                    </div>
                    <select id="blog_cid" name="blog_cid" class="mediashelf-related-select">
                        <option value="">No related post</option>
                        <?php foreach ($relatedPosts as $post): ?>
                            <option
                                value="<?php echo Admin::h($post['cid']); ?>"
                                data-title="<?php echo Admin::h($post['title']); ?>"
                                data-type="<?php echo Admin::h($post['type']); ?>"
                                data-created="<?php echo Admin::h($post['created'] ? date('Y-m-d', $post['created']) : ''); ?>"
                                data-url="<?php echo Admin::h($post['url']); ?>"
                                data-excerpt="<?php echo Admin::h($post['excerpt']); ?>"
                                <?php if ($selectedPostCid === (int) $post['cid']) echo 'selected'; ?>
                            >
                                <?php echo Admin::h('#' . $post['cid'] . ' [' . $post['type'] . '] ' . $post['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mediashelf-related-preview<?php if (!$selectedPostPreview) echo ' is-empty'; ?>" id="mediashelf_related_preview">
                        <?php if ($selectedPostPreview): ?>
                            <p class="mediashelf-related-preview-title"><?php echo Admin::h($selectedPostPreview['title']); ?></p>
                            <?php if ($selectedPostPreview['url']): ?>
                                <p class="mediashelf-related-preview-meta"><a href="<?php echo Admin::h($selectedPostPreview['url']); ?>" target="_blank" rel="noopener noreferrer">Open post preview</a></p>
                            <?php endif; ?>
                            <?php if ($selectedPostPreview['excerpt']): ?>
                                <p class="mediashelf-related-preview-excerpt"><?php echo Admin::h($selectedPostPreview['excerpt']); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            No related post selected.
                        <?php endif; ?>
                    </div>
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
?>
<script>
(function () {
    var resetButton = document.getElementById('mediashelf_post_filter_reset');
    var filterWrap = document.querySelector('.mediashelf-related-filters');
    var select = document.getElementById('blog_cid');
    var preview = document.getElementById('mediashelf_related_preview');
    var typeFilter = document.getElementById('post_type_filter');
    var categoryFilter = document.getElementById('post_category_filter');
    var publishedFilter = document.getElementById('post_published_filter');
    var searchFilter = document.getElementById('post_search_filter');
    var searchTimer = null;

    function filterUrl(reset) {
        var url = new URL(filterWrap.getAttribute('data-filter-base'), window.location.href);
        if (!reset) {
            url.searchParams.set('mediashelf_related_ajax', '1');
            url.searchParams.set('selected_cid', select.value || '');
            url.searchParams.set('post_type', typeFilter.value);
            url.searchParams.set('post_category', categoryFilter.value);
            url.searchParams.set('post_published', publishedFilter.value);
            url.searchParams.set('post_search', searchFilter.value);
        } else {
            url.searchParams.set('mediashelf_related_ajax', '1');
            url.searchParams.set('selected_cid', select.value || '');
            url.searchParams.delete('post_type');
            url.searchParams.delete('post_category');
            url.searchParams.delete('post_published');
            url.searchParams.delete('post_search');
        }

        return url.toString();
    }

    function text(value) {
        return value || '';
    }

    function postLabel(post) {
        return '#' + post.cid + ' [' + post.type + '] ' + post.title;
    }

    function setOptionData(option, post) {
        option.setAttribute('data-title', text(post.title));
        option.setAttribute('data-type', text(post.type));
        option.setAttribute('data-created', post.created ? formatDate(post.created) : '');
        option.setAttribute('data-url', text(post.url));
        option.setAttribute('data-excerpt', text(post.excerpt));
    }

    function formatDate(timestamp) {
        var date = new Date(parseInt(timestamp, 10) * 1000);
        if (isNaN(date.getTime())) {
            return '';
        }

        return [
            date.getFullYear(),
            padDate(date.getMonth() + 1),
            padDate(date.getDate())
        ].join('-');
    }

    function padDate(value) {
        value = String(value);
        return value.length < 2 ? '0' + value : value;
    }

    function replaceOptions(posts, selectedCid) {
        var currentValue = selectedCid || select.value;
        select.innerHTML = '';

        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'No related post';
        select.appendChild(empty);

        posts.forEach(function (post) {
            var option = document.createElement('option');
            option.value = String(post.cid);
            option.textContent = postLabel(post);
            setOptionData(option, post);
            if (String(post.cid) === String(currentValue)) {
                option.selected = true;
            }
            select.appendChild(option);
        });

        if (currentValue && select.value !== String(currentValue)) {
            select.value = '';
        }

        renderPreview();
    }

    function refreshOptions(reset) {
        if (!filterWrap || !select) {
            return;
        }

        var request = new XMLHttpRequest();
        request.open('GET', filterUrl(reset), true);
        request.onreadystatechange = function () {
            if (request.readyState !== 4) {
                return;
            }

            if (request.status < 200 || request.status >= 300) {
                return;
            }

            try {
                var data = JSON.parse(request.responseText);
                replaceOptions(data.posts || [], data.selected_cid || '');
            } catch (e) {
            }
        };
        request.send();
    }

    function debounceSearch() {
        if (searchTimer) {
            window.clearTimeout(searchTimer);
        }

        searchTimer = window.setTimeout(function () {
            refreshOptions(false);
        }, 350);
    }

    function renderPreview() {
        var option = select.options[select.selectedIndex];
        if (!option || !option.value) {
            preview.className = 'mediashelf-related-preview is-empty';
            preview.textContent = 'No related post selected.';
            return;
        }

        var title = text(option.getAttribute('data-title'));
        var type = text(option.getAttribute('data-type'));
        var created = text(option.getAttribute('data-created'));
        var url = text(option.getAttribute('data-url'));
        var excerpt = text(option.getAttribute('data-excerpt'));

        preview.className = 'mediashelf-related-preview';
        preview.innerHTML = '';

        var titleNode = document.createElement('p');
        titleNode.className = 'mediashelf-related-preview-title';
        titleNode.textContent = title;
        preview.appendChild(titleNode);

        var metaNode = document.createElement('p');
        metaNode.className = 'mediashelf-related-preview-meta';
        metaNode.textContent = [type, created].filter(Boolean).join(' - ');
        preview.appendChild(metaNode);

        if (url) {
            var linkNode = document.createElement('p');
            linkNode.className = 'mediashelf-related-preview-meta';
            var link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = 'Open post preview';
            linkNode.appendChild(link);
            preview.appendChild(linkNode);
        }

        if (excerpt) {
            var excerptNode = document.createElement('p');
            excerptNode.className = 'mediashelf-related-preview-excerpt';
            excerptNode.textContent = excerpt;
            preview.appendChild(excerptNode);
        }
    }

    if (typeFilter) {
        typeFilter.addEventListener('change', function () {
            refreshOptions(false);
        });
    }

    if (categoryFilter) {
        categoryFilter.addEventListener('change', function () {
            refreshOptions(false);
        });
    }

    if (publishedFilter) {
        publishedFilter.addEventListener('change', function () {
            refreshOptions(false);
        });
    }

    if (searchFilter) {
        searchFilter.addEventListener('input', debounceSearch);
    }

    if (resetButton && filterWrap) {
        resetButton.addEventListener('click', function () {
            typeFilter.value = 'post';
            categoryFilter.value = '0';
            publishedFilter.value = 'all';
            searchFilter.value = '';
            refreshOptions(true);
        });
    }

    if (select && preview) {
        select.addEventListener('change', renderPreview);
    }
})();
</script>
<?php
include 'footer.php';
?>
