<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Traits\SchemaHelpers;
use CodeIgniter\Database\Migration;

/**
 * Downloadable brochures, what they are attached to, and who asked for them.
 *
 * THREE TABLES, ONE IDEA
 *
 * `brochures` is the file. `brochure_targets` says which page offers it.
 * `brochure_leads` is the person who downloaded it. Keeping the attachment in
 * its own table is what lets one brochure serve several pages without the file
 * being uploaded again per page — and what lets a page be re-pointed at a new
 * edition without touching the page itself.
 *
 * WHY A POLYMORPHIC TARGET
 *
 * A brochure can hang off a category, an occasion/collection, or a content
 * page. Those are three tables with nothing in common, and three nullable
 * foreign keys on one row would allow two of them to be set at once — a state
 * with no correct answer. `target_type` + `target_id`, unique together, makes
 * "this page has one brochure" a property of the schema rather than of the
 * code that writes it.
 *
 * The trade is that the database cannot enforce the reference. Deletion is
 * therefore handled in BrochureService, and a target row pointing at something
 * gone resolves to the default rather than erroring.
 *
 * THE FILE IS NOT UNDER public/
 *
 * `path` is relative to WRITEPATH, not FCPATH. A brochure behind a lead form
 * that is also fetchable at its own URL is not gated at all — the form would
 * be decoration, and the first person to share the direct link would remove it
 * for everyone. Downloads are streamed by the controller.
 */
class Brochures extends Migration
{
    use SchemaHelpers;

    public function up(): void
    {
        // ------------------------------------------------------ brochures
        $this->forge->addField(array_merge(
            $this->pk(),
            $this->str('title'),
            $this->text('description'),
            // Relative to writable/, e.g. uploads/brochures/2026/09/abc.pdf
            $this->str('path', 255),
            // What it was called on the way in — used for the download name,
            // because a customer should not receive "8f2c1e....pdf".
            $this->str('filename', 191),
            $this->str('mime', 60, true),
            $this->int('bytes', 0, false, true),
            /*
             * The fallback for every page with nothing of its own. Enforced as
             * "at most one" in BrochureModel rather than by a unique index: a
             * partial index on `is_default = 1` is not portable, and a plain
             * unique key would allow only a single non-default brochure.
             */
            $this->flag('is_default', 0),
            $this->flag('is_active', 1),
            // Cheap to read on the listing; the leads table is the real record.
            $this->int('download_count', 0, false, true),
            $this->stamps(true, true),
        ));
        $this->forge->addKey('id', true);
        $this->forge->addKey('is_default');
        $this->forge->addKey('is_active');
        $this->forge->createTable('brochures', true);

        // ------------------------------------------------ brochure_targets
        $this->forge->addField(array_merge(
            $this->pk(),
            $this->ref('brochure_id'),
            $this->enum('target_type', ['category', 'collection', 'page']),
            $this->ref('target_id'),
            $this->stamps(),
        ));
        $this->forge->addKey('id', true);
        // One brochure per page. Two would make "which one" a coin toss.
        $this->forge->addUniqueKey(['target_type', 'target_id'], 'brochure_targets_page');
        $this->forge->addKey('brochure_id');
        /*
         * CASCADE is right here and nowhere else in this module: a target row
         * is meaningless without its brochure, and it carries nothing that has
         * to survive. Leads deliberately do NOT cascade — see below.
         */
        $this->forge->addForeignKey('brochure_id', 'brochures', 'id', '', 'CASCADE');
        $this->forge->createTable('brochure_targets', true);

        // -------------------------------------------------- brochure_leads
        $this->forge->addField(array_merge(
            $this->pk(),
            /*
             * NULL when the brochure has since been deleted. The lead is the
             * valuable record — somebody asked for something and left their
             * details — and it must outlive the file. `brochure_title` is a
             * snapshot for exactly that reason, the same way order lines keep
             * a name.
             */
            $this->ref('brochure_id', true),
            $this->str('brochure_title', 191),
            // Set when the person was signed in. Guests have none, and they
            // are the majority.
            $this->ref('customer_id', true),
            $this->str('name'),
            $this->str('email'),
            $this->str('phone', 40),
            $this->text('notes'),
            // Where they asked from, so the shop can tell a Diwali enquiry
            // from a corporate one without reading the brochure title.
            $this->str('source_type', 40, true),
            $this->ref('source_id', true),
            $this->str('source_url', 255, true),
            $this->requestFingerprint(),
            $this->stamps(false),
        ));
        $this->forge->addKey('id', true);
        $this->forge->addKey('brochure_id');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('email');
        // The listing is newest-first and that is its only sort.
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('brochure_id', 'brochures', 'id', '', 'SET NULL');
        $this->forge->createTable('brochure_leads', true);
    }

    public function down(): void
    {
        // Children first: the foreign keys will not let the parent go.
        $this->forge->dropTable('brochure_leads', true);
        $this->forge->dropTable('brochure_targets', true);
        $this->forge->dropTable('brochures', true);
    }
}
