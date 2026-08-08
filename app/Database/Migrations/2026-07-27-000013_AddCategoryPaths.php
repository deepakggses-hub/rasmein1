<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Nested categories addressable at the site root.
 *
 * WHY A MATERIALISED PATH
 *
 * A category's URL is now its full ancestry — /gifting/teas-infusions/green.
 * Resolving that by walking parent_id means one query per level on every page
 * load, and the depth is unbounded. Storing the assembled path in an indexed
 * column makes a lookup a single equality match however deep the tree goes.
 *
 * The cost is that the path must be rebuilt when a category is renamed or
 * reparented, and that rebuild has to reach every descendant. CategoryModel
 * owns that, and it is the reason nothing should write `path` directly.
 *
 * `depth` is stored alongside because it is needed constantly — for indenting
 * the admin dropdown, for enforcing a maximum, for ordering a tree — and
 * deriving it from the path on every row is wasteful.
 */
class AddCategoryPaths extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('categories', [
            'path' => [
                'type'       => 'VARCHAR',
                'constraint' => 512,
                'null'       => true,
                'after'      => 'slug',
            ],
            'depth' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'default'    => 0,
                'null'       => false,
                'after'      => 'path',
            ],
        ]);

        // Unique: two categories cannot occupy the same URL. Not a primary key,
        // because a soft-deleted row keeps its path until it is purged and we
        // would rather that collision surface as a validation message than as a
        // constraint violation.
        $this->db->query('CREATE UNIQUE INDEX idx_categories_path ON categories (path)');
        $this->db->query('CREATE INDEX idx_categories_parent_depth ON categories (parent_id, depth, sort_order)');

        // Existing categories are all top level, so their path is their slug.
        $this->db->query('UPDATE categories SET path = slug, depth = 0 WHERE parent_id IS NULL');
        $this->db->query(
            'UPDATE categories c JOIN categories p ON p.id = c.parent_id
             SET c.path = CONCAT(p.slug, "/", c.slug), c.depth = 1
             WHERE c.parent_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX idx_categories_path ON categories');
        $this->db->query('DROP INDEX idx_categories_parent_depth ON categories');
        $this->forge->dropColumn('categories', ['path', 'depth']);
    }
}
