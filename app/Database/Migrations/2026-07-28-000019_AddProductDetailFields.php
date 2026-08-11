<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The fields the product page design needs.
 *
 * Everything the detail page shows should be enterable when adding a product.
 * Before this, the accordions had nowhere to read from — a "Care instructions"
 * heading with no field behind it is a promise the admin panel cannot keep, and
 * the person filling in a product has no way to discover what is missing.
 *
 * `rating_average` and `review_count` are DISPLAY figures a shop types in, not
 * anything computed from customer reviews — there is no reviews table. The admin
 * form says so plainly, because a number that looks derived and is not will
 * eventually mislead whoever inherits this.
 */
class AddProductDetailFields extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('products', [
            // The second half of the eyebrow: "WEDDING · SIGNATURE HAMPER".
            'eyebrow_label' => [
                'type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'material',
            ],
            'composition' => [
                'type' => 'TEXT', 'null' => true, 'after' => 'description',
            ],
            'packaging_note' => [
                'type' => 'TEXT', 'null' => true, 'after' => 'composition',
            ],
            'care_note' => [
                'type' => 'TEXT', 'null' => true, 'after' => 'packaging_note',
            ],
            'personalisation_note' => [
                'type' => 'TEXT', 'null' => true, 'after' => 'care_note',
            ],
            'rating_average' => [
                'type' => 'DECIMAL', 'constraint' => '2,1', 'null' => true, 'after' => 'personalisation_note',
            ],
            'review_count' => [
                'type' => 'INT', 'default' => 0, 'null' => false, 'after' => 'rating_average',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('products', [
            'eyebrow_label', 'composition', 'packaging_note',
            'care_note', 'personalisation_note', 'rating_average', 'review_count',
        ]);
    }
}
