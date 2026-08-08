<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A category slug is unique WITHIN ITS PARENT, not across the whole table.
 *
 * The original schema made `slug` globally unique, which was correct while
 * every category was top level. Once categories nest, the slug is only one
 * segment of the address: /tea/gifts and /coffee/gifts are two different pages
 * that both legitimately use "gifts". The global constraint made that
 * impossible — and it failed as a raw "Duplicate entry" from the database
 * rather than as anything a person could act on.
 *
 * What must stay unique is the assembled PATH, and that already has its own
 * unique index from the previous migration. The slug index becomes a plain
 * lookup index.
 */
class CategorySlugPerParent extends Migration
{
    public function up(): void
    {
        // Find the actual index name rather than assuming: it differs between
        // schemas created by the forge and ones tweaked by hand.
        foreach ($this->db->query(
            "SHOW INDEX FROM categories WHERE Column_name = 'slug' AND Non_unique = 0"
        )->getResultArray() as $index) {
            $this->db->query('DROP INDEX `' . $index['Key_name'] . '` ON categories');
        }

        // Still worth an index — slugs are looked up on the legacy /shop/{slug}
        // redirect path.
        $existing = $this->db->query(
            "SHOW INDEX FROM categories WHERE Column_name = 'slug'"
        )->getResultArray();

        if ($existing === []) {
            $this->db->query('CREATE INDEX idx_categories_slug ON categories (slug)');
        }
    }

    public function down(): void
    {
        // Restoring the global constraint can fail if nested duplicates exist by
        // then, which is exactly why it was removed. Roll back the data first.
        $this->db->query('DROP INDEX idx_categories_slug ON categories');
        $this->db->query('CREATE UNIQUE INDEX slug ON categories (slug)');
    }
}
