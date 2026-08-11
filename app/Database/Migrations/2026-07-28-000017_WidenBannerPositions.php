<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Three more banner slots for the homepage design.
 *
 * `position` is an ENUM in the schema, not a plain varchar. Widening only the
 * model's in_list rule made the application accept a value the database then
 * silently truncated — MySQL reported "Data truncated for column 'position'"
 * and the row landed with an empty position, so the banner existed but appeared
 * nowhere. Validation in two places has to be changed in both.
 */
class WidenBannerPositions extends Migration
{
    private const POSITIONS = "'home_hero','home_strip','home_feature','home_client','home_gallery','category_top','gift_builder'";

    public function up(): void
    {
        $this->db->query(
            'ALTER TABLE banners MODIFY COLUMN position ENUM(' . self::POSITIONS . ") NOT NULL DEFAULT 'home_hero'"
        );
    }

    public function down(): void
    {
        // Rows using a removed slot would be truncated on the way back, so
        // move them somewhere valid first.
        $this->db->query(
            "UPDATE banners SET position = 'home_strip'
             WHERE position IN ('home_feature','home_client','home_gallery')"
        );

        $this->db->query(
            "ALTER TABLE banners MODIFY COLUMN position
             ENUM('home_hero','home_strip','category_top','gift_builder') NOT NULL DEFAULT 'home_hero'"
        );
    }
}
