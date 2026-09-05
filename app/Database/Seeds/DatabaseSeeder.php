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
        $this->call(AttributeSeeder::class);
        /*
         * The real catalogue. Runs AFTER AttributeSeeder — it looks values up
         * by label, so the attributes must exist first — and after
         * CatalogueSeeder, whose demo products it deliberately replaces.
         */
        $this->call(ProductCatalogueSeeder::class);

        /*
         * Variants, built from the attributes the catalogue just attached.
         *
         * MUST run after ProductCatalogueSeeder, which truncates products — and
         * it was the only seeder missing from this chain, so `db:seed
         * DatabaseSeeder` produced zero variants, the selector never rendered
         * and the export's variant columns were empty with nothing saying why.
         *
         * ProductCatalogueSeeder calls it too, for the case where that seeder is
         * run alone. It truncates its own tables first, so running twice is
         * harmless.
         */
        $this->call(VariantSeeder::class);
        // The landing page at /collection.
        $this->call(CollectionsPageSeeder::class);
        $this->call(AboutPageSeeder::class);
        $this->call(ContactPageSeeder::class);
        $this->call(ChromeSeeder::class);
        // Sign-in copy and the Google credential keys. Missing from this chain
        // once meant a fresh install had no google_auth_* rows at all, so
        // isConfigured() was false and the button silently never rendered.
        $this->call(AuthContentSeeder::class);
        $this->call(MailSettingSeeder::class);
        $this->call(EmailTemplateSeeder::class);
        $this->call(ContentSeeder::class);

        echo "\nDone.\n";
    }
}
