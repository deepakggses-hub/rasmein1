<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Export the catalogue, one row per VARIANT.
 *
 * A product-per-row export cannot answer "which sizes does the gold one come
 * in" — the question this exists for. One row per variant makes the whole grid
 * sortable and filterable in a spreadsheet, which is how a buyer actually
 * checks a catalogue over.
 *
 * Streamed rather than assembled in memory. Four hundred rows would fit
 * comfortably today, but an export that falls over the week the catalogue grows
 * is an export nobody trusts.
 */
class CatalogueExport extends AdminController
{
    /**
     * The chooser: what to export, and in which format.
     *
     * A screen rather than two bare buttons, because "everything" is rarely
     * what someone wants. Checking one category over is a different job from
     * auditing the whole catalogue, and a 460-row sheet is the wrong tool for
     * it.
     */
    public function index()
    {
        if ($denied = $this->deny('catalogue.manage')) {
            return $denied;
        }

        $db = db_connect();

        return $this->adminPage('admin/export/index', [
            // Counts up front, so nobody exports an empty selection and has to
            // open the file to find out.
            'categories' => $db->table('categories c')
                ->select('c.id, c.name, COUNT(p.id) AS n', false)
                ->join('products p', 'p.category_id = c.id AND p.deleted_at IS NULL', 'left')
                ->where('c.deleted_at', null)
                ->groupBy('c.id')->orderBy('c.sort_order', 'ASC')
                ->get()->getResultArray(),

            'occasions' => $db->table('collections col')
                ->select('col.id, col.name, COUNT(cp.product_id) AS n', false)
                ->join('collection_products cp', 'cp.collection_id = col.id', 'left')
                ->where('col.type', 'occasion')
                ->where('col.deleted_at', null)
                ->groupBy('col.id')->orderBy('col.sort_order', 'ASC')
                ->get()->getResultArray(),

            'total' => $db->table('products')->where('deleted_at', null)->countAllResults(),
        ], 'Export the catalogue');
    }

    /** Excel-readable, one row per variant. */
    public function csv()
    {
        if ($denied = $this->deny('catalogue.manage')) {
            return $denied;
        }

        $scope      = $this->scope();
        $rows       = $this->rows($scope);
        $attributes = $this->attributeColumns();

        service('audit')->log(
            'exported',
            'catalogue',
            'export',
            null,
            $scope['label'] . ' — ' . count($rows) . ' variant row(s)'
        );

        // The filename says what is inside, so three exports in a downloads
        // folder are still tellable apart a week later.
        $name = 'rasmein-' . $scope['slug'] . '-' . date('Y-m-d') . '.csv';

        return $this->response
            ->setContentType('text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setBody($this->buildCsv($rows, $attributes));
    }

    /** A dump that can be replayed into another database. */
    public function sql()
    {
        if ($denied = $this->deny('catalogue.manage')) {
            return $denied;
        }

        $db    = db_connect();
        $scope = $this->scope();

        /*
         * A scoped SQL dump lists only the products asked for, but ALL the
         * categories, attributes and values — the rows those products point at.
         * A dump missing its own foreign keys will not replay, which makes it
         * useless for the one thing a SQL export is for.
         */
        $productIds = array_column($this->rows($scope), 'product_id');
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        $out = "-- Rasmein catalogue export\n"
            . '-- ' . $scope['label'] . "\n"
            . '-- ' . date('Y-m-d H:i') . "\n"
            . "--\n"
            . "-- Replays into an empty database that already has the schema.\n"
            . "-- Wrapped in a transaction: a dump that half-loads is worse than\n"
            . "-- one that fails, because nothing says which half arrived.\n\n"
            . "SET FOREIGN_KEY_CHECKS = 0;\nSTART TRANSACTION;\n\n";

        /*
         * Parents before children, so the file is replayable even with the
         * checks turned back on — the SET above is a convenience, not a licence
         * to emit rows in any order.
         */
        foreach ([
            'categories', 'attributes', 'attribute_values',
            'products', 'product_attributes', 'product_variants', 'variant_values',
        ] as $table) {
            $out .= '-- ' . $table . "\n";
            /*
             * A whole-catalogue dump clears the table first so a replay is a
             * true replacement. A SCOPED dump must not — deleting rows it was
             * never given would silently destroy the rest of the catalogue on
             * the machine it is replayed into.
             */
            if ($scope['where'] === null) {
                $out .= 'DELETE FROM `' . $table . "`;\n";
            }

            $query = $db->table($table);

            // Narrow the product-shaped tables; leave the lookups whole.
            if ($productIds !== [] && $scope['where'] !== null) {
                if ($table === 'products') {
                    $query->whereIn('id', $productIds);
                } elseif ($table === 'product_attributes') {
                    $query->whereIn('product_id', $productIds);
                } elseif ($table === 'product_variants') {
                    $query->whereIn('product_id', $productIds);
                } elseif ($table === 'variant_values') {
                    $query->whereIn('variant_id', static function ($sub) use ($productIds) {
                        return $sub->select('id')->from('product_variants')
                            ->whereIn('product_id', $productIds);
                    });
                }
            }

            $result = $query->get();
            $count  = 0;

            foreach ($result->getResultArray() as $row) {
                $columns = array_map(static fn (string $c): string => '`' . $c . '`', array_keys($row));

                $values = array_map(
                    static fn ($v): string => $v === null ? 'NULL' : $db->escape((string) $v),
                    array_values($row)
                );

                $out .= 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES ('
                    . implode(', ', $values) . ");\n";
                $count++;
            }

            $out .= '-- ' . $count . " row(s)\n\n";
        }

        $out .= "COMMIT;\nSET FOREIGN_KEY_CHECKS = 1;\n";

        service('audit')->log('exported', 'catalogue', 'export', null, 'SQL dump — ' . $scope['label']);

        $name = 'rasmein-' . $scope['slug'] . '-' . date('Y-m-d') . '.sql';

        return $this->response
            ->setContentType('application/sql; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setBody($out);
    }

    // -----------------------------------------------------------------

    /**
     * What was asked for: everything, one category, or one occasion.
     *
     * Validated against the database rather than trusted — an id in a query
     * string is a request, not a fact, and a bad one should narrow to nothing
     * rather than widen to everything.
     *
     * @return array{where: ?callable, label: string, slug: string}
     */
    private function scope(): array
    {
        $db         = db_connect();
        $categoryId = (int) $this->request->getGet('category');
        $occasionId = (int) $this->request->getGet('occasion');

        if ($categoryId > 0) {
            $row = $db->table('categories')->where('id', $categoryId)->get()->getRowArray();

            if ($row !== null) {
                return [
                    'where' => static function ($builder) use ($categoryId) {
                        $builder->where('p.category_id', $categoryId);
                    },
                    'label' => 'Category: ' . $row['name'],
                    'slug'  => 'category-' . $row['slug'],
                ];
            }
        }

        if ($occasionId > 0) {
            $row = $db->table('collections')->where('id', $occasionId)->get()->getRowArray();

            if ($row !== null) {
                return [
                    'where' => static function ($builder) use ($occasionId) {
                        // A subquery, not a join: joining the pivot would
                        // multiply every variant row by its occasions.
                        $builder->whereIn('p.id', static function ($sub) use ($occasionId) {
                            return $sub->select('cp.product_id')
                                ->from('collection_products cp')
                                ->where('cp.collection_id', $occasionId);
                        });
                    },
                    'label' => 'Occasion: ' . $row['name'],
                    'slug'  => 'occasion-' . $row['slug'],
                ];
            }
        }

        return ['where' => null, 'label' => 'Whole catalogue', 'slug' => 'catalogue'];
    }

    /**
     * Every variant, with its product and its attribute values.
     *
     * A LEFT JOIN on variants, deliberately: a product with none must still
     * appear, or an export used to check the catalogue would quietly omit
     * exactly the products someone forgot to set up.
     *
     * @param array{where: ?callable} $scope
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $scope = ['where' => null]): array
    {
        $db = db_connect();

        $builder = $db->table('products p')
            ->select('p.id AS product_id, p.sku AS product_sku, p.name AS product_name, p.slug,'
                . ' p.price AS product_price, p.stock_qty AS product_stock, p.material,'
                . ' p.is_active AS product_active, p.sale_mode, c.name AS category,'
                . ' v.id AS variant_id, v.sku AS variant_sku, v.label AS variant_label,'
                . ' v.variant_key, v.price AS variant_price, v.compare_at_price,'
                . ' v.stock_qty AS variant_stock, v.is_default, v.is_active AS variant_active,'
                . ' v.image AS variant_image', false)
            ->join('categories c', 'c.id = p.category_id', 'left')
            ->join('product_variants v', 'v.product_id = p.id', 'left')
            ->where('p.deleted_at', null)
            ->orderBy('p.sku', 'ASC')
            ->orderBy('v.sort_order', 'ASC');

        if (($scope['where'] ?? null) !== null) {
            ($scope['where'])($builder);
        }

        $rows = $builder->get()->getResultArray();

        // Every variant's values, in ONE query rather than one per row.
        $values = [];

        foreach ($db->table('variant_values vv')
            ->select('vv.variant_id, a.name AS attribute, av.label', false)
            ->join('attribute_values av', 'av.id = vv.value_id')
            ->join('attributes a', 'a.id = av.attribute_id')
            ->orderBy('a.sort_order', 'ASC')
            ->get()->getResultArray() as $row) {
            $values[(int) $row['variant_id']][(string) $row['attribute']][] = (string) $row['label'];
        }

        foreach ($rows as $i => $row) {
            $rows[$i]['values'] = $values[(int) $row['variant_id']] ?? [];
        }

        return $rows;
    }

    /**
     * A column per attribute, so the sheet can be filtered on colour or size.
     *
     * @return array<int, string>
     */
    private function attributeColumns(): array
    {
        return array_column(
            db_connect()->table('attributes')->select('name')
                ->orderBy('sort_order', 'ASC')->get()->getResultArray(),
            'name'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string>               $attributes
     */
    private function buildCsv(array $rows, array $attributes): string
    {
        $handle = fopen('php://temp', 'w+');

        /*
         * A UTF-8 BOM. Without it Excel on Windows reads the file as the local
         * codepage and turns rupees and inch marks into mojibake — which makes
         * a correct export look like corrupt data.
         */
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, array_merge([
            'Product SKU', 'Product', 'Category', 'Material',
            'Variant SKU', 'Variant', 'URL',
        ], $attributes, [
            'Price', 'Price is', 'Was', 'Stock', 'Default', 'Variant on', 'Product on', 'Journey', 'Image',
        ]));

        foreach ($rows as $row) {
            $hasVariant = $row['variant_id'] !== null;

            // The price the customer actually sees, and where it came from —
            // "inherited" is the answer to "why are these two the same".
            $price = $row['variant_price'] !== null
                ? (float) $row['variant_price']
                : (float) $row['product_price'];

            $line = [
                $row['product_sku'],
                $row['product_name'],
                $row['category'] ?? '',
                $row['material'] ?? '',
                $row['variant_sku'] ?? '',
                $row['variant_label'] ?? '(no variants)',
                $hasVariant
                    ? site_url('product/' . $row['slug'] . '/' . $row['variant_key'])
                    : site_url('product/' . $row['slug']),
            ];

            foreach ($attributes as $attribute) {
                $line[] = implode(', ', $row['values'][$attribute] ?? []);
            }

            $line = array_merge($line, [
                // Unformatted: a spreadsheet must be able to sum this column,
                // and "₹3,500" is text.
                number_format($price, 2, '.', ''),
                $row['variant_price'] !== null ? 'set on variant' : 'inherited',
                $row['compare_at_price'] !== null ? number_format((float) $row['compare_at_price'], 2, '.', '') : '',
                $hasVariant ? (int) $row['variant_stock'] : (int) $row['product_stock'],
                (int) ($row['is_default'] ?? 0) === 1 ? 'yes' : '',
                $hasVariant ? ((int) $row['variant_active'] === 1 ? 'yes' : 'no') : '',
                (int) $row['product_active'] === 1 ? 'yes' : 'no',
                $row['sale_mode'],
                $row['variant_image'] ?? '',
            ]);

            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
