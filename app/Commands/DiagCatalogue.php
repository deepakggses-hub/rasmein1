<?php
declare(strict_types=1);
namespace App\Commands;
use App\Models\ProductModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DiagCatalogue extends BaseCommand
{
    protected $group = 'Rasmein';
    protected $name = 'rasmein:diag-catalogue';
    protected $description = 'Smoke-test catalogue filtering, search, sorting and pagination.';

    public function run(array $params)
    {
        $tests = [
            'no filters'            => ['filters' => [], 'sort' => null],
            'category = 1'          => ['filters' => ['category' => 1], 'sort' => null],
            'collection = 1'        => ['filters' => ['collection' => 1], 'sort' => null],
            'price 300-600'         => ['filters' => ['min_price' => 300, 'max_price' => 600], 'sort' => null],
            'in stock only'         => ['filters' => ['in_stock' => true], 'sort' => null],
            'giftable only'         => ['filters' => ['giftable' => true], 'sort' => null],
            'search "chocolate"'    => ['filters' => ['q' => 'chocolate'], 'sort' => null],
            'search "choc" (prefix)' => ['filters' => ['q' => 'choc'], 'sort' => null],
            'search "tea"'          => ['filters' => ['q' => 'tea'], 'sort' => null],
            'search "ce" (short)'   => ['filters' => ['q' => 'ce'], 'sort' => null],
            'search "almond tin"'   => ['filters' => ['q' => 'almond tin'], 'sort' => null],
            'search nonsense'       => ['filters' => ['q' => 'zzzqqq'], 'sort' => null],
            'sort price_asc'        => ['filters' => [], 'sort' => 'price_asc'],
            'sort price_desc'       => ['filters' => [], 'sort' => 'price_desc'],
            'sort newest'           => ['filters' => [], 'sort' => 'newest'],
            'sort name_asc'         => ['filters' => [], 'sort' => 'name_asc'],
            'sort INJECTION'        => ['filters' => [], 'sort' => 'price; DROP TABLE products'],
            'combined'              => ['filters' => ['category' => 2, 'in_stock' => true, 'max_price' => 900], 'sort' => 'price_asc'],
        ];

        foreach ($tests as $label => $t) {
            try {
                $m = model(ProductModel::class);
                $rows = $m->applyFilters($t['filters'])->applySort($t['sort'])->findAll(50);
                $first = $rows[0] ?? null;
                $detail = count($rows) . ' rows';
                if ($first !== null) {
                    $detail .= '  first: ' . mb_substr($first->name, 0, 28) . ' (' . $first->formattedPrice() . ')';
                }
                CLI::write(sprintf('  [ ok ] %-24s %s', $label, $detail), 'green');
            } catch (\Throwable $e) {
                CLI::write(sprintf('  [FAIL] %-24s %s', $label, $e->getMessage()), 'red');
            }
        }

        // Pagination
        try {
            $m = model(ProductModel::class);
            $page = $m->applyFilters([])->applySort('price_asc')->paginate(6, 'default', 2);
            CLI::write(sprintf('  [ ok ] %-24s page 2 = %d rows, total %d, pages %d',
                'paginate(6) page 2', count($page), $m->pager->getTotal('default'), $m->pager->getPageCount('default')), 'green');
        } catch (\Throwable $e) {
            CLI::write(sprintf('  [FAIL] %-24s %s', 'paginate', $e->getMessage()), 'red');
        }

        // Price range + related
        try {
            $r = model(ProductModel::class)->priceRange();
            CLI::write(sprintf('  [ ok ] %-24s %s – %s', 'priceRange', rs_money($r['min']), rs_money($r['max'])), 'green');
        } catch (\Throwable $e) {
            CLI::write(sprintf('  [FAIL] %-24s %s', 'priceRange', $e->getMessage()), 'red');
        }

        try {
            $m = model(ProductModel::class);
            $p = $m->findVisibleBySlug('dark-chocolate-72');
            $rel = $p !== null ? $m->related($p, 4) : [];
            CLI::write(sprintf('  [ ok ] %-24s %d rows', 'related()', count($rel)), 'green');
        } catch (\Throwable $e) {
            CLI::write(sprintf('  [FAIL] %-24s %s', 'related()', $e->getMessage()), 'red');
        }

        // Confirm the table survived the injection attempt
        $count = model(ProductModel::class)->countAllResults();
        CLI::write(sprintf('  products table still intact: %d rows', $count), $count > 0 ? 'green' : 'red');
    }
}
