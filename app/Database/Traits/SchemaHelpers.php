<?php

declare(strict_types=1);

namespace App\Database\Traits;

/**
 * Shared column builders for migrations.
 *
 * Keeps every table consistent: same PK type, same timestamp columns,
 * same money precision. Change it here, not in twelve places.
 */
trait SchemaHelpers
{
    /** Table options — InnoDB everywhere so foreign keys actually work. */
    protected array $tableAttributes = ['ENGINE' => 'InnoDB'];

    /** Auto-increment primary key. */
    protected function pk(string $name = 'id'): array
    {
        return [
            $name => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
        ];
    }

    /** Foreign-key column (the constraint itself is added separately). */
    protected function ref(string $name, bool $nullable = false): array
    {
        return [
            $name => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => $nullable,
            ],
        ];
    }

    protected function str(string $name, int $length = 191, bool $nullable = false, ?string $default = null): array
    {
        $col = ['type' => 'VARCHAR', 'constraint' => $length, 'null' => $nullable];

        if ($default !== null) {
            $col['default'] = $default;
        }

        return [$name => $col];
    }

    protected function text(string $name, bool $nullable = true): array
    {
        return [$name => ['type' => 'TEXT', 'null' => $nullable]];
    }

    /** Money. 12,2 is enough for INR order totals without float drift. */
    protected function money(string $name, bool $nullable = false, string $default = '0.00', string $precision = '12,2'): array
    {
        $col = ['type' => 'DECIMAL', 'constraint' => $precision, 'null' => $nullable];

        if (! $nullable) {
            $col['default'] = $default;
        }

        return [$name => $col];
    }

    protected function int(string $name, int $default = 0, bool $nullable = false, bool $unsigned = false): array
    {
        $col = ['type' => 'INT', 'constraint' => 11, 'null' => $nullable, 'unsigned' => $unsigned];

        if (! $nullable) {
            $col['default'] = $default;
        }

        return [$name => $col];
    }

    /** Boolean flag stored as TINYINT(1) — MySQL has no real bool. */
    protected function flag(string $name, int $default = 1): array
    {
        return [$name => ['type' => 'TINYINT', 'constraint' => 1, 'default' => $default]];
    }

    protected function enum(string $name, array $values, ?string $default = null, bool $nullable = false): array
    {
        $col = ['type' => 'ENUM', 'constraint' => $values, 'null' => $nullable];

        if ($default !== null) {
            $col['default'] = $default;
        }

        return [$name => $col];
    }

    protected function datetime(string $name, bool $nullable = true): array
    {
        return [$name => ['type' => 'DATETIME', 'null' => $nullable]];
    }

    /** SEO fields — every public-facing entity gets these. */
    protected function seoFields(): array
    {
        return $this->str('meta_title', 191, true)
            + $this->str('meta_description', 255, true);
    }

    /**
     * created_at / updated_at / deleted_at.
     * deleted_at is what CI4's soft-delete looks for.
     */
    protected function stamps(bool $updated = true, bool $softDelete = false): array
    {
        $fields = $this->datetime('created_at');

        if ($updated) {
            $fields += $this->datetime('updated_at');
        }

        if ($softDelete) {
            $fields += $this->datetime('deleted_at');
        }

        return $fields;
    }

    /** IP + user agent, for audit trails and abuse investigation. */
    protected function requestFingerprint(): array
    {
        return $this->str('ip_address', 45, true)
            + $this->str('user_agent', 255, true);
    }
}
