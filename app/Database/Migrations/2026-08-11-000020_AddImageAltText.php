<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Alt text for the images that had nowhere to put it.
 *
 * `product_images` and `banners` already had the column — but the product form
 * never rendered a field for it, so every product image on the site has been
 * shipping with an empty alt. Categories, occasions and testimonials had no
 * column at all.
 *
 * Alt text is not decoration. It is what a screen reader announces, what shows
 * when an image fails to load, and what a search engine reads. An empty alt is
 * correct ONLY for an image that adds nothing beyond adjacent text — and that
 * should be a decision someone made, not a field that was never built.
 */
class AddImageAltText extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('categories', [
            'alt_text' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true, 'after' => 'image'],
        ]);

        $this->forge->addColumn('collections', [
            'alt_text' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true, 'after' => 'image'],
        ]);

        $this->forge->addColumn('testimonials', [
            'alt_text' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true, 'after' => 'image'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('categories', 'alt_text');
        $this->forge->dropColumn('collections', 'alt_text');
        $this->forge->dropColumn('testimonials', 'alt_text');
    }
}
