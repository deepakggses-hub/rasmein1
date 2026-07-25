<?php
declare(strict_types=1);
namespace App\Commands;
use App\Models\ProductModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DiagSearch extends BaseCommand
{
    protected $group = 'Rasmein';
    protected $name = 'rasmein:diag-search';
    protected $description = 'Throw hostile and awkward input at product search.';

    private int $failures = 0;

    public function run(array $params)
    {
        $this->probe("<script>alert(1)</script>");
        $this->probe("\" onmouseover=\"alert(1)");
        $this->probe("tea (loose)");
        $this->probe("+++");
        $this->probe("---");
        $this->probe("chocolate");
        $this->probe("choc");
        $this->probe("tea");
        $this->probe("ce");
        $this->probe("almond");
        $this->probe("~test");
        $this->probe("@user");
        $this->probe("a*b*c");
        $this->probe("100%");
        $this->probe("O'Brien");
        $this->probe("\u0926\u093e\u0930\u094d\u091c\u093f\u0932\u093f\u0902\u0917");
        $this->probe("tea AND chocolate");
        $this->probe("xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx");
        $this->probe("   ");
        $this->probe("(((");
        $this->probe("blue pottery");
        $this->probe("RSM-CH-001");
        $this->probe("caf\u00e9");
        CLI::newLine();
        CLI::write($this->failures === 0
            ? '  No query crashed. Search input is safe.'
            : sprintf('  %d input(s) caused an error.', $this->failures),
            $this->failures === 0 ? 'green' : 'red');
    }

    private function probe(string $term): void
    {
        $label = $term === '' ? '(empty)' : mb_substr(str_replace(["\n", "\r"], ' ', $term), 0, 26);
        try {
            $rows = model(ProductModel::class)->applyFilters(['q' => $term])->applySort(null)->findAll(20);
            CLI::write(sprintf('  [ ok ] %-28s %d rows', $label, count($rows)), 'green');
        } catch (\Throwable $e) {
            $this->failures++;
            CLI::write(sprintf('  [FAIL] %-28s %s', $label, mb_substr($e->getMessage(), 0, 70)), 'red');
        }
    }
}
