<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Runs every seeder in dependency order.
 *
 *   php spark db:seed DatabaseSeeder
 *
 * Every seeder is idempotent — re-running it adds what is missing and leaves
 * existing rows alone, so it is safe against a database that already has data.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        echo "\nSeeding Rasmein…\n\n";

        $this->call(SettingsSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(CatalogueSeeder::class);
        $this->call(GiftBoxSeeder::class);
        $this->call(ContentSeeder::class);

        echo "\nDone.\n";
    }
}
