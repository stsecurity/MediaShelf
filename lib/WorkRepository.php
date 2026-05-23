<?php

namespace TypechoPlugin\MediaShelf\Lib;

class WorkRepository
{
    const CATEGORIES = [
        'anime' => 'Anime',
        'manga' => 'Manga',
        'game' => 'Game',
        'music' => 'Music',
        'novel' => 'Novel',
        'visual_novel' => 'Visual Novel',
        'other' => 'Other',
    ];

    const STATUSES = [
        'draft' => 'Draft',
        'published' => 'Published',
        'hidden' => 'Hidden',
    ];

    const FAVORITE_LEVELS = [
        'normal' => 'Normal',
        'liked' => 'Liked',
        'loved' => 'Loved',
        'favorite' => 'Favorite',
    ];

    public function findForAdmin(array $filters = [])
    {
        $db = Database::db();
        $select = $db->select()->from('table.mediashelf_works');

        if (!empty($filters['search'])) {
            $keyword = '%' . $filters['search'] . '%';
            $select->where('title LIKE ? OR original_title LIKE ?', $keyword, $keyword);
        }

        if (!empty($filters['category']) && isset(self::CATEGORIES[$filters['category']])) {
            $select->where('category = ?', $filters['category']);
        }

        if (!empty($filters['status']) && isset(self::STATUSES[$filters['status']])) {
            $select->where('status = ?', $filters['status']);
        }

        $sort = isset($filters['sort']) ? $filters['sort'] : 'updated';
        switch ($sort) {
            case 'title':
                $select->order('title', 'ASC');
                break;
            case 'category':
                $select->order('category', 'ASC')->order('sort_order', 'ASC');
                break;
            case 'sort_order':
                $select->order('sort_order', 'ASC')->order('category', 'ASC')->order('title', 'ASC');
                break;
            case 'updated':
            default:
                $select->order('updated_at', 'DESC');
                break;
        }

        $select->limit(100);

        return $db->fetchAll($select);
    }

    public function findPublished(array $filters = [], $limit = 0)
    {
        $db = Database::db();
        $select = $db->select()
            ->from('table.mediashelf_works')
            ->where('status = ?', 'published');

        if (!empty($filters['search'])) {
            $keyword = '%' . $filters['search'] . '%';
            $select->where('title LIKE ? OR original_title LIKE ? OR review_text LIKE ?', $keyword, $keyword, $keyword);
        }

        if (!empty($filters['category']) && isset(self::CATEGORIES[$filters['category']])) {
            $select->where('category = ?', $filters['category']);
        }

        $select->order('sort_order', 'ASC')
            ->order('category', 'ASC')
            ->order('title', 'ASC')
            ->order('updated_at', 'DESC');

        if ((int) $limit > 0 && empty($filters['tag'])) {
            $select->limit((int) $limit);
        }

        $works = $db->fetchAll($select);

        if (!empty($filters['tag'])) {
            $tag = (string) $filters['tag'];
            $works = array_filter($works, function ($work) use ($tag) {
                $tags = $this->decodeJsonList(isset($work['tags_json']) ? $work['tags_json'] : '');
                return in_array($tag, $tags, true);
            });
            $works = array_values($works);
        }

        if ((int) $limit > 0 && count($works) > (int) $limit) {
            $works = array_slice($works, 0, (int) $limit);
        }

        return $works;
    }

    public function publicFacets()
    {
        $works = $this->findPublished();
        $categories = [];
        $tags = [];

        foreach ($works as $work) {
            if (!empty($work['category']) && isset(self::CATEGORIES[$work['category']])) {
                $categories[$work['category']] = self::CATEGORIES[$work['category']];
            }

            foreach ($this->decodeJsonList(isset($work['tags_json']) ? $work['tags_json'] : '') as $tag) {
                $tags[$tag] = $tag;
            }
        }

        ksort($categories);
        ksort($tags);

        return [
            'categories' => $categories,
            'tags' => array_values($tags),
        ];
    }

    public function blogPreview($cid)
    {
        $cid = (int) $cid;
        if ($cid <= 0) {
            return null;
        }

        $db = Database::db();
        $row = $db->fetchRow($db->select('cid', 'title', 'slug', 'type', 'text', 'created')
            ->from('table.contents')
            ->where('cid = ?', $cid)
            ->where('status = ?', 'publish')
            ->where('type IN ?', ['post', 'page'])
            ->limit(1));

        if (!$row) {
            return null;
        }

        return [
            'title' => isset($row['title']) ? (string) $row['title'] : '',
            'url' => $this->contentPermalink($row),
            'excerpt' => $this->contentExcerpt(isset($row['text']) ? (string) $row['text'] : ''),
        ];
    }

    public function relatedPostOptions(array $filters = [], $selectedCid = 0)
    {
        $db = Database::db();
        $type = isset($filters['type']) ? (string) $filters['type'] : 'post';
        $category = isset($filters['category']) ? (int) $filters['category'] : 0;
        $search = trim((string) (isset($filters['search']) ? $filters['search'] : ''));

        if (!in_array($type, ['post', 'page', 'all'], true)) {
            $type = 'post';
        }

        $cids = [];
        if ($category > 0) {
            $rows = $db->fetchAll($db->select('cid')
                ->from('table.relationships')
                ->where('mid = ?', $category));
            foreach ($rows as $row) {
                $cids[] = (int) $row['cid'];
            }

            if (!$cids) {
                return $selectedCid > 0 ? $this->selectedRelatedPostOnly($selectedCid) : [];
            }
        }

        $select = $db->select('cid', 'title', 'slug', 'type', 'text', 'created')
            ->from('table.contents')
            ->where('status = ?', 'publish');

        if ($type === 'all') {
            $select->where('type IN ?', ['post', 'page']);
        } else {
            $select->where('type = ?', $type);
        }

        if ($search !== '') {
            $select->where('title LIKE ?', '%' . $search . '%');
        }

        if ($cids) {
            $select->where('cid IN ?', $cids);
        }

        $select->order('created', 'DESC')->limit(80);
        $rows = $db->fetchAll($select);

        if ($selectedCid > 0 && !$this->rowListHasCid($rows, $selectedCid)) {
            $selected = $this->selectedRelatedPostOnly($selectedCid);
            if ($selected) {
                $rows = array_merge($selected, $rows);
            }
        }

        return array_map([$this, 'relatedPostOption'], $rows);
    }

    public function relatedPostCategories()
    {
        $db = Database::db();
        $rows = $db->fetchAll($db->select('mid', 'name')
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->order('order', 'ASC')
            ->order('name', 'ASC'));

        $categories = [];
        foreach ($rows as $row) {
            $categories[] = [
                'mid' => (int) $row['mid'],
                'name' => isset($row['name']) ? (string) $row['name'] : '',
            ];
        }

        return $categories;
    }

    public function nextSortOrder()
    {
        $db = Database::db();
        $row = $db->fetchRow($db->select('sort_order')
            ->from('table.mediashelf_works')
            ->order('sort_order', 'DESC')
            ->limit(1));

        return max(1, (int) (isset($row['sort_order']) ? $row['sort_order'] : 0) + 1);
    }

    public function findById($id)
    {
        $db = Database::db();
        $row = $db->fetchRow($db->select()
            ->from('table.mediashelf_works')
            ->where('id = ?', (int) $id)
            ->limit(1));

        return $row ?: null;
    }

    public function findPublishedBySlug($slug)
    {
        $db = Database::db();
        $row = $db->fetchRow($db->select()
            ->from('table.mediashelf_works')
            ->where('slug = ?', (string) $slug)
            ->where('status = ?', 'published')
            ->limit(1));

        return $row ?: null;
    }

    public function create(array $input)
    {
        $data = $this->validate($input);
        $now = time();
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $this->assertSlugAvailable($data['slug']);

        $db = Database::db();
        return (int) $db->query($db->insert('table.mediashelf_works')->rows($data));
    }

    public function createImportedDraft(array $source)
    {
        $externalIds = isset($source['external_ids']) && is_array($source['external_ids']) ? $source['external_ids'] : [];
        $provider = isset($source['provider']) ? (string) $source['provider'] : '';
        if ($provider !== '') {
            $externalIds['provider'] = $provider;
        }

        $input = [
            'title' => isset($source['title']) ? $source['title'] : '',
            'slug' => '',
            'category' => isset($source['category']) ? $source['category'] : 'other',
            'original_title' => isset($source['original_title']) ? $source['original_title'] : '',
            'cover_url' => isset($source['cover_url']) ? $source['cover_url'] : '',
            'release_date' => isset($source['release_date']) ? $source['release_date'] : '',
            'creators' => $this->listToText(isset($source['creators']) ? $source['creators'] : []),
            'description' => isset($source['description']) ? $source['description'] : '',
            'review_text' => '',
            'blog_cid' => '',
            'blog_url' => '',
            'external_ids_json' => json_encode($externalIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'favorite_level' => 'normal',
            'tags' => $this->listToText(isset($source['tags']) ? $source['tags'] : []),
            'sort_order' => $this->nextSortOrder(),
            'status' => 'draft',
        ];

        $data = $this->validate($input);
        $data['slug'] = $this->uniqueSlug($data['slug']);
        $data['source_payload_json'] = $this->sourcePayloadJson($source);

        $now = time();
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $db = Database::db();
        return (int) $db->query($db->insert('table.mediashelf_works')->rows($data));
    }

    public function update($id, array $input)
    {
        $id = (int) $id;
        if (!$this->findById($id)) {
            throw new \InvalidArgumentException('Work not found.');
        }

        $data = $this->validate($input);
        $data['updated_at'] = time();

        $this->assertSlugAvailable($data['slug'], $id);

        $db = Database::db();
        $db->query($db->update('table.mediashelf_works')
            ->rows($data)
            ->where('id = ?', $id));
    }

    public function delete($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid work id.');
        }

        $db = Database::db();
        $db->query($db->delete('table.mediashelf_works')->where('id = ?', $id));
    }

    public function updateStatus($id, $status)
    {
        $id = (int) $id;
        $status = (string) $status;
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid work id.');
        }

        if (!isset(self::STATUSES[$status])) {
            throw new \InvalidArgumentException('Invalid status.');
        }

        $db = Database::db();
        $db->query($db->update('table.mediashelf_works')
            ->rows([
                'status' => $status,
                'updated_at' => time(),
            ])
            ->where('id = ?', $id));
    }

    public function updateSortOrders(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, function ($id) {
            return $id > 0;
        }));

        if (!$ids) {
            throw new \InvalidArgumentException('No works were provided for sorting.');
        }

        $db = Database::db();
        $sortOrder = 1;
        $now = time();
        foreach ($ids as $id) {
            $db->query($db->update('table.mediashelf_works')
                ->rows([
                    'sort_order' => $sortOrder,
                    'updated_at' => $now,
                ])
                ->where('id = ?', $id));
            $sortOrder++;
        }
    }

    public function blankWork()
    {
        return [
            'id' => 0,
            'slug' => '',
            'category' => 'anime',
            'title' => '',
            'original_title' => '',
            'cover_url' => '',
            'release_date' => '',
            'creators_json' => '[]',
            'description' => '',
            'review_text' => '',
            'blog_cid' => null,
            'blog_url' => '',
            'blog_preview_json' => null,
            'external_ids_json' => '{}',
            'source_payload_json' => null,
            'favorite_level' => 'normal',
            'tags_json' => '[]',
            'sort_order' => $this->nextSortOrder(),
            'status' => 'draft',
        ];
    }

    public function validate(array $input)
    {
        $title = $this->cleanText(isset($input['title']) ? $input['title'] : '', 255);
        if ($title === '') {
            throw new \InvalidArgumentException('Title is required.');
        }

        $slug = $this->slug(isset($input['slug']) ? $input['slug'] : '');
        if ($slug === '') {
            $slug = $this->slug($title);
        }

        if ($slug === '') {
            throw new \InvalidArgumentException('Slug is required.');
        }

        $category = isset($input['category']) ? (string) $input['category'] : 'other';
        if (!isset(self::CATEGORIES[$category])) {
            throw new \InvalidArgumentException('Invalid category.');
        }

        $status = isset($input['status']) ? (string) $input['status'] : 'draft';
        if (!isset(self::STATUSES[$status])) {
            throw new \InvalidArgumentException('Invalid status.');
        }

        $favorite = isset($input['favorite_level']) ? (string) $input['favorite_level'] : 'normal';
        if (!isset(self::FAVORITE_LEVELS[$favorite])) {
            throw new \InvalidArgumentException('Invalid favorite level.');
        }

        $coverUrl = $this->cleanUrl(isset($input['cover_url']) ? $input['cover_url'] : '', 'Cover URL');
        $blogUrl = $this->cleanUrl(isset($input['blog_url']) ? $input['blog_url'] : '', 'Blog URL');
        $blogCid = $this->nullablePositiveInt(isset($input['blog_cid']) ? $input['blog_cid'] : null, 'Blog post ID');

        return [
            'slug' => $slug,
            'category' => $category,
            'title' => $title,
            'original_title' => $this->cleanText(isset($input['original_title']) ? $input['original_title'] : '', 255),
            'cover_url' => $coverUrl,
            'release_date' => $this->cleanReleaseDate(isset($input['release_date']) ? $input['release_date'] : ''),
            'creators_json' => $this->listToJson(isset($input['creators']) ? $input['creators'] : ''),
            'description' => $this->cleanLongText(isset($input['description']) ? $input['description'] : ''),
            'review_text' => $this->cleanLongText(isset($input['review_text']) ? $input['review_text'] : ''),
            'blog_cid' => $blogCid,
            'blog_url' => $blogUrl,
            'blog_preview_json' => null,
            'external_ids_json' => $this->objectJson(isset($input['external_ids_json']) ? $input['external_ids_json'] : ''),
            'source_payload_json' => null,
            'favorite_level' => $favorite,
            'tags_json' => $this->listToJson(isset($input['tags']) ? $input['tags'] : ''),
            'sort_order' => (int) (isset($input['sort_order']) ? $input['sort_order'] : 0),
            'status' => $status,
        ];
    }

    private function assertSlugAvailable($slug, $excludeId = 0)
    {
        $db = Database::db();
        $select = $db->select()
            ->from('table.mediashelf_works')
            ->where('slug = ?', $slug)
            ->limit(1);

        if ($excludeId) {
            $select->where('id <> ?', (int) $excludeId);
        }

        if ($db->fetchRow($select)) {
            throw new \InvalidArgumentException('Slug is already used by another work.');
        }
    }

    private function uniqueSlug($slug)
    {
        $base = $slug !== '' ? $slug : 'work';
        $candidate = $base;
        $suffix = 2;

        while ($this->slugExists($candidate)) {
            $candidate = substr($base, 0, 190) . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugExists($slug)
    {
        $db = Database::db();
        return (bool) $db->fetchRow($db->select()
            ->from('table.mediashelf_works')
            ->where('slug = ?', $slug)
            ->limit(1));
    }

    private function cleanText($value, $maxLength)
    {
        $value = trim(str_replace("\0", '', (string) $value));
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }

    private function cleanLongText($value)
    {
        return trim(str_replace("\0", '', (string) $value));
    }

    private function cleanUrl($value, $label)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException($label . ' must be a valid URL.');
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            throw new \InvalidArgumentException($label . ' must use http or https.');
        }

        return $value;
    }

    private function cleanReleaseDate($value)
    {
        $value = $this->cleanText($value, 32);
        if ($value !== '' && !preg_match('/^[0-9A-Za-z ._:\\/-]+$/', $value)) {
            throw new \InvalidArgumentException('Release date contains invalid characters.');
        }

        return $value;
    }

    private function nullablePositiveInt($value, $label)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!ctype_digit((string) $value)) {
            throw new \InvalidArgumentException($label . ' must be a positive integer.');
        }

        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private function listToJson($value)
    {
        $items = preg_split('/[\r\n,]+/', (string) $value);
        $items = array_map('trim', $items);
        $items = array_filter($items, function ($item) {
            return $item !== '';
        });
        $items = array_values(array_unique($items));

        return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function listToText($value)
    {
        if (!is_array($value)) {
            return (string) $value;
        }

        $items = array_map('strval', $value);
        return implode("\n", $items);
    }

    public function decodeJsonList($json)
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $decoded), function ($item) {
            return $item !== '';
        }));
    }

    private function contentPermalink(array $row)
    {
        try {
            if (class_exists('\Typecho\Router')) {
                $type = isset($row['type']) && $row['type'] === 'page' ? 'page' : 'post';
                $path = \Typecho\Router::url($type, $row);
                if ($path !== '#') {
                    return $path;
                }
            }
        } catch (\Throwable $e) {
            return '';
        }

        return '';
    }

    private function contentExcerpt($text)
    {
        $text = preg_replace('/<!--more-->.*$/is', '', (string) $text);
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
        if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 140) {
            return mb_substr($text, 0, 140, 'UTF-8') . '...';
        }

        if (!function_exists('mb_strlen') && strlen($text) > 140) {
            return substr($text, 0, 140) . '...';
        }

        return $text;
    }

    private function selectedRelatedPostOnly($cid)
    {
        $row = $this->contentRow((int) $cid);
        return $row ? [$row] : [];
    }

    private function contentRow($cid)
    {
        if ($cid <= 0) {
            return null;
        }

        $db = Database::db();
        $row = $db->fetchRow($db->select('cid', 'title', 'slug', 'type', 'text', 'created')
            ->from('table.contents')
            ->where('cid = ?', $cid)
            ->where('status = ?', 'publish')
            ->where('type IN ?', ['post', 'page'])
            ->limit(1));

        return $row ?: null;
    }

    private function rowListHasCid(array $rows, $cid)
    {
        foreach ($rows as $row) {
            if ((int) $row['cid'] === (int) $cid) {
                return true;
            }
        }

        return false;
    }

    private function relatedPostOption(array $row)
    {
        return [
            'cid' => (int) $row['cid'],
            'title' => isset($row['title']) ? (string) $row['title'] : '',
            'type' => isset($row['type']) ? (string) $row['type'] : '',
            'created' => (int) (isset($row['created']) ? $row['created'] : 0),
            'url' => $this->contentPermalink($row),
            'excerpt' => $this->contentExcerpt(isset($row['text']) ? (string) $row['text'] : ''),
        ];
    }

    private function objectJson($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '{}';
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('External IDs must be a valid JSON object.');
        }

        if (array_values($decoded) === $decoded) {
            throw new \InvalidArgumentException('External IDs must be a JSON object, not a list.');
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function sourcePayloadJson(array $source)
    {
        $payload = [
            'imported_at' => time(),
            'provider' => isset($source['provider']) ? $source['provider'] : '',
            'source_url' => isset($source['source_url']) ? $source['source_url'] : '',
            'mapped' => $source,
            'raw' => isset($source['source_payload']) ? $source['source_payload'] : null,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Unable to encode imported provider payload.');
        }

        return $json;
    }

    private function slug($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9_\\-]+/', '-', $value);
        $value = trim($value, '-_');

        return substr($value, 0, 200);
    }
}
