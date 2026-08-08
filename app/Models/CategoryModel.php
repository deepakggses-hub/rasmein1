<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Category;
use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table          = 'categories';
    protected $primaryKey     = 'id';
    protected $returnType     = Category::class;
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'parent_id', 'name', 'slug', 'path', 'depth', 'description', 'image',
        'is_featured', 'sort_order', 'is_active',
        'meta_title', 'meta_description',
    ];

    protected $beforeInsert = ['fillPath'];
    protected $beforeUpdate = ['fillPath'];

    protected $validationRules = [
        'id' => 'permit_empty|is_natural_no_zero',   // required by CI4: {id} placeholder
        'name'             => 'required|min_length[2]|max_length[120]',
        // Deliberately NOT globally unique. With nesting, the slug is only one
        // segment of the address, so /tea/gifts and /coffee/gifts are two
        // distinct pages that both legitimately use the slug "gifts". What must
        // be unique is the assembled PATH — enforced by a unique index on that
        // column and checked in the controller so it surfaces as a message
        // rather than a constraint violation.
        'slug'             => 'required|max_length[160]|regex_match[/^[a-z0-9-]+$/]',
        'path'             => 'permit_empty|max_length[512]',
        'parent_id'        => 'permit_empty|is_natural_no_zero',
        'sort_order'       => 'permit_empty|is_integer',
        'meta_title'       => 'permit_empty|max_length[191]',
        'meta_description' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'slug' => [
            'regex_match' => 'The URL slug may only contain lowercase letters, numbers and hyphens.',
            'is_unique'   => 'Another category already uses that URL slug.',
        ],
    ];

    /** @return list<Category> */
    public function activeTopLevel(): array
    {
        return $this->where('is_active', 1)
            ->groupStart()->where('parent_id', null)->orWhere('parent_id', 0)->groupEnd()
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /** @return list<Category> */
    public function featured(int $limit = 6): array
    {
        return $this->where('is_active', 1)
            ->where('is_featured', 1)
            ->orderBy('sort_order', 'ASC')
            ->findAll($limit);
    }

    public function findActiveBySlug(string $slug): ?Category
    {
        return $this->where('slug', $slug)->where('is_active', 1)->first();
    }

    /**
     * Categories with a live count of purchasable products.
     *
     * @return list<Category>
     */
    public function withProductCounts(bool $activeOnly = true, ?int $limit = null): array
    {
        $builder = $this->select('categories.*, COUNT(products.id) AS product_count')
            ->join('products', 'products.category_id = categories.id AND products.is_active = 1 AND products.deleted_at IS NULL', 'left', false)
            ->groupBy('categories.id')
            ->orderBy('categories.sort_order', 'ASC');

        if ($activeOnly) {
            $builder->where('categories.is_active', 1);
        }

        return $builder->findAll($limit ?? 0);
    }


    /**
     * Fill path and depth whenever they are not supplied.
     *
     * The admin controller computes these itself, because it needs the value
     * before saving in order to check for collisions. Everything ELSE — the
     * catalogue seeder above all — inserts a category without them, and on a
     * fresh install that left every path NULL and every category URL dead.
     * Caught by a from-zero rebuild, not by any test of the admin screen.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function fillPath(array $data): array
    {
        if (! isset($data['data']) || ! is_array($data['data'])) {
            return $data;
        }

        $row = $data['data'];

        // Already computed by the caller: leave it alone.
        if (isset($row['path']) && $row['path'] !== '' && $row['path'] !== null) {
            return $data;
        }

        if (! isset($row['slug']) || (string) $row['slug'] === '') {
            return $data;
        }

        $parentId = isset($row['parent_id']) && $row['parent_id'] !== null
            ? (int) $row['parent_id']
            : null;

        $data['data']['path']  = $this->buildPath($parentId, (string) $row['slug']);
        $data['data']['depth'] = $this->depthUnder($parentId);

        return $data;
    }

    // =================================================================
    // The tree
    // =================================================================

    /**
     * How deep the tree may go, counting the root as 0.
     *
     * "N levels" in practice means "as many as anyone will sensibly use". A cap
     * exists because a URL assembled from ancestry grows with depth, a dropdown
     * indented past a handful of levels stops being readable, and an accidental
     * loop would otherwise recurse until it exhausted memory. Five is well past
     * what a gifting catalogue needs.
     */
    public const MAX_DEPTH = 4;

    /**
     * Segments that can never be a top-level category slug, because a route
     * already claims them.
     *
     * Computed from the registered routes rather than hand-listed: a hard-coded
     * list drifts the moment a route is added, and the failure — a category
     * silently shadowing /cart — would be discovered by a customer.
     *
     * @return list<string>
     */
    /**
     * Delegates to RootUrlService.
     *
     * Kept as a thin wrapper so existing callers still work, but the list has
     * ONE home: categories and occasions both claim root addresses, and two
     * copies of this logic would eventually disagree.
     *
     * @return list<string>
     */
    public function reservedSlugs(): array
    {
        return service('rootUrls')->reserved();
    }

    /** Would setting $parentId on $id create a loop, or exceed the depth cap? */
    public function wouldCycle(?int $id, ?int $parentId): bool
    {
        if ($parentId === null || $id === null) {
            return false;
        }

        if ($id === $parentId) {
            return true;
        }

        // Walk up from the proposed parent. If we meet ourselves, the move
        // would detach a whole branch from the root and orphan it.
        $seen  = [];
        $cursor = $parentId;

        while ($cursor !== null) {
            if ($cursor === $id) {
                return true;
            }

            // Defensive: a loop already in the data must not hang this walk.
            if (isset($seen[$cursor])) {
                return true;
            }

            $seen[$cursor] = true;

            $row    = $this->select('id, parent_id')->where('id', $cursor)->first();
            $parent = $row === null ? null : $row->parent_id;
            $cursor = $parent !== null ? (int) $parent : null;

            if (count($seen) > self::MAX_DEPTH + 2) {
                return true;
            }
        }

        return false;
    }

    /** Depth a category would sit at under $parentId. */
    public function depthUnder(?int $parentId): int
    {
        if ($parentId === null) {
            return 0;
        }

        $parent = $this->select('id, depth')->where('id', $parentId)->first();

        return $parent === null ? 0 : (int) $parent->depth + 1;
    }

    /** The full URL path a category would occupy. */
    public function buildPath(?int $parentId, string $slug): string
    {
        if ($parentId === null) {
            return $slug;
        }

        $parent = $this->select('id, path')->where('id', $parentId)->first();

        return $parent === null || (string) ($parent->path ?? '') === ''
            ? $slug
            : $parent->path . '/' . $slug;
    }

    /**
     * Recompute path and depth for a category and everything beneath it.
     *
     * Called after a rename or a reparent. Descendants are found by path
     * prefix, which is the whole reason the path is stored — otherwise this
     * would be a recursive walk of unknown breadth.
     */
    public function rebuildSubtree(int $id, ?string $knownOldPath = null): int
    {
        $node = $this->find($id);

        if ($node === null) {
            return 0;
        }

        /*
         * The caller must pass the path this category had BEFORE it was saved.
         *
         * By the time this runs, the controller has already written the new
         * path to the row — so reading it here returns the new value, the
         * "did the path change?" test compares it against itself, and the
         * method returns early having moved nothing. The descendants are then
         * stranded at addresses whose parent no longer exists. Found in
         * testing: renaming a parent left four subcategories 404ing.
         */
        $oldPath   = $knownOldPath ?? (string) ($node->path ?? '');
        $parentId  = $node->parent_id !== null ? (int) $node->parent_id : null;
        $newPath   = $this->buildPath($parentId, (string) $node->slug);
        $newDepth  = $this->depthUnder($parentId);
        // Derive the old depth from the old path rather than the row, for the
        // same reason: the row has already been updated.
        $oldDepth  = $oldPath === '' ? 0 : substr_count($oldPath, '/');

        $this->builder()->where('id', $id)->update(['path' => $newPath, 'depth' => $newDepth]);

        if ($oldPath === '' || $oldPath === $newPath) {
            return 1;
        }

        // Rewrite the prefix on every descendant in one statement. The depth
        // shift is the same for all of them, since the whole branch moved
        // together.
        $shift = $newDepth - $oldDepth;

        $this->db->query(
            'UPDATE categories
             SET path = CONCAT(?, SUBSTRING(path, ?)), depth = depth + ?
             WHERE path LIKE ?',
            [$newPath, strlen($oldPath) + 1, $shift, $oldPath . '/%']
        );

        return 1 + $this->db->affectedRows();
    }

    /** A category by its full path, e.g. "gifting/teas-infusions". */
    public function findByPath(string $path, bool $activeOnly = true): ?object
    {
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        $query = $this->where('path', $path);

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->first();
    }

    /**
     * A category and everything below it.
     *
     * Used when listing products: opening a parent should show what is in its
     * children too, or a top-level category looks empty while its subcategories
     * hold everything.
     *
     * @return list<int>
     */
    public function descendantIds(int $id, bool $includeSelf = true): array
    {
        $node = $this->select('id, path')->where('id', $id)->first();
        $ids  = $includeSelf ? [$id] : [];

        if ($node === null || (string) ($node->path ?? '') === '') {
            return $ids;
        }

        foreach ($this->select('id')->like('path', $node->path . '/', 'after')->findAll() as $row) {
            $ids[] = (int) $row->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * The whole tree, depth-first, ready to render as an indented list.
     *
     * @return array<int, \App\Entities\Category>
     */
    public function tree(bool $activeOnly = false): array
    {
        $query = $this->orderBy('path', 'ASC');

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        // Ordering by path IS the depth-first order, since a child's path is its
        // parent's path plus a separator.
        return $query->findAll();
    }

    /**
     * Ancestors of a category, root first — the breadcrumb trail.
     *
     * @return array<int, \App\Entities\Category>
     */
    public function ancestors(int $id): array
    {
        $node = $this->find($id);

        if ($node === null || (string) ($node->path ?? '') === '') {
            return [];
        }

        $segments = explode('/', (string) $node->path);
        array_pop($segments);

        $paths = [];
        $built = '';

        foreach ($segments as $segment) {
            $built  = $built === '' ? $segment : $built . '/' . $segment;
            $paths[] = $built;
        }

        if ($paths === []) {
            return [];
        }

        return $this->whereIn('path', $paths)->orderBy('depth', 'ASC')->findAll();
    }

    /**
     * Recompute every path and depth in the table, root downwards.
     *
     * A safety net, and the repair for rows written straight through the query
     * builder — the catalogue seeder does exactly that, so on a fresh install
     * every path was NULL and no category URL worked at all. Model callbacks
     * cannot help there, because nothing goes through the model.
     *
     * Cheap enough to call after any bulk import: a shop has tens of
     * categories, not millions.
     */
    public function rebuildAllPaths(): int
    {
        $updated = 0;

        // Level by level, so a parent always has its path before its children
        // are asked for theirs.
        for ($depth = 0; $depth <= self::MAX_DEPTH; $depth++) {
            $rows = $depth === 0
                ? $this->builder()->where('parent_id', null)->get()->getResultArray()
                : $this->builder()->where('parent_id IS NOT NULL', null, false)
                    ->whereIn('parent_id', function ($sub) use ($depth) {
                        return $sub->select('id')->from('categories')->where('depth', $depth - 1);
                    })->get()->getResultArray();

            foreach ($rows as $row) {
                $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
                $path     = $this->buildPath($parentId, (string) $row['slug']);

                if ((string) ($row['path'] ?? '') === $path && (int) $row['depth'] === $depth) {
                    continue;
                }

                $this->builder()->where('id', $row['id'])->update(['path' => $path, 'depth' => $depth]);
                $updated++;
            }
        }

        return $updated;
    }

    /** Direct children of a category, or of the root when null. @return array<int, \App\Entities\Category> */
    public function childrenOf(?int $parentId, bool $activeOnly = true): array
    {
        $query = $parentId === null
            ? $this->where('parent_id', null)
            : $this->where('parent_id', $parentId);

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();
    }
}
