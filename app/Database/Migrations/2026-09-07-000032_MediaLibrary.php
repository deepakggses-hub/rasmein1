<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The media library.
 *
 * Every upload gets a row here, so a picture can be found and used again
 * instead of being re-uploaded. Until now an image existed only as a path
 * inside whatever record used it — there was no way to ask "what have we
 * already got" without reading six tables.
 *
 * `path` is unique: the same file listed twice would show as two entries the
 * shop has to choose between, with no way to tell them apart.
 */
class MediaLibrary extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            // Relative to public/, e.g. uploads/content/2026/09/thing.jpg
            'path' => ['type' => 'VARCHAR', 'constraint' => 255],
            // What it was called on the way in. Searchable, and the only clue
            // to what a picture IS before someone writes alt text.
            'filename'   => ['type' => 'VARCHAR', 'constraint' => 191],
            'alt_text'   => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'mime'       => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'bytes'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'width'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'height'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // Which uploader put it here — 'content', 'products', 'banners'.
            // Lets the picker offer a sensible default view per screen.
            'collection' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'content'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('path');
        $this->forge->addKey('collection');
        // Newest first is the default view, and search hits this too.
        $this->forge->addKey('created_at');
        $this->forge->createTable('media', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('media', true);
    }
}
