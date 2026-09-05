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
        $this->call(CouponSeeder::class);
        $this->call(BrandSettingSeeder::class);
        $this->call(DesignSettingSeeder::class);
        $this->call(HomeContentSeeder::class);
        // Sign-in copy and the Google credential keys. Missing from this chain
        // meant a fresh install had no google_auth_* rows at all, so
        // isConfigured() was false and the button silently never rendered.
        // In the chain, or a fresh install has no contact page at all — the
        // mistake made with AuthContentSeeder.
        $this->call(AttributeSeeder::class);
        /*
         * The real catalogue. Runs AFTER AttributeSeeder — it looks values up
         * by label, so the attributes must exist first — and after
         * CatalogueSeeder, whose demo products it deliberately replaces.
         */
        $this->call(ProductCatalogueSeeder::class);
        $this->call(ContactPageSeeder::class);
        $this->call(AuthContentSeeder::class);
        $this->call(MailSettingSeeder::class);
        $this->call(EmailTemplateSeeder::class);
        $this->call(ContentSeeder::class);

        echo "\nDone.\n";
    }
}
