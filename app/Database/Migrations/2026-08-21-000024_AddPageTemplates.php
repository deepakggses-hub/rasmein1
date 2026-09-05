<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Page templates.
 *
 * A page has been one thing: a title and a block of rich text. That is right
 * for Privacy Policy and wrong for Contact, which is a set of cards, an address,
 * a photograph and a list of questions — none of which a shop should have to
 * build by hand-writing HTML into an editor.
 *
 * `template` says which layout to render. `data` holds whatever that layout
 * needs, as JSON, because the fields differ per template and a column per field
 * would mean a migration every time a template is added.
 *
 * JSON rather than a `page_blocks` table: these fields are read as one whole,
 * always, and never queried across pages. A join table would buy nothing and
 * cost a query.
 */
class AddPageTemplates extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('pages', [
            'template' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'default'    => 'standard',
                'null'       => false,
                'after'      => 'slug',
            ],
            'data' => [
                'type'  => 'JSON',
                'null'  => true,
                'after' => 'content',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('pages', ['template', 'data']);
    }
}
