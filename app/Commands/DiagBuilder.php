<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\GiftBoxModel;
use App\Models\ProductModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Tests the gift-box builder's SERVER-SIDE rules — the ones the UI also
 * enforces, which is precisely why they need testing independently of it.
 */
class DiagBuilder extends BaseCommand
{
    protected $group       = 'Rasmein';
    protected $name        = 'rasmein:diag-builder';
    protected $description = 'Test gift-box capacity, eligibility and personalisation rules.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        CLI::newLine();

        try {
            $this->run_tests();
        } catch (Throwable $e) {
            $this->fail++;
            CLI::write('  UNCAUGHT: ' . $e->getMessage(), 'red');
            CLI::write('    ' . $e->getFile() . ':' . $e->getLine(), 'dark_gray');
        } finally {
            $this->cleanup();
        }

        CLI::newLine();
        CLI::write(sprintf('  %d passed, %d failed', $this->pass, $this->fail), $this->fail === 0 ? 'green' : 'red');
        CLI::newLine();

        return $this->fail === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function check(string $label, bool $ok, string $detail = ''): void
    {
        $ok ? $this->pass++ : $this->fail++;
        CLI::write(sprintf('  [%s] %-38s %s', $ok ? ' ok ' : 'FAIL', $label, $detail), $ok ? 'green' : 'red');
    }

    private function builder(): \App\Services\GiftBoxBuilderService
    {
        return service('builder');
    }

    private function run_tests(): void
    {
        $this->cleanup();

        CLI::write('  Starting and resuming', 'white');
        CLI::write('  ' . str_repeat('-', 58), 'dark_gray');

        $bad = $this->builder()->startOrResume('no-such-box');
        $this->check('unknown box slug refused', ! $bad['ok'], $bad['message']);

        $start = $this->builder()->startOrResume('classic-tray');
        $this->check('box started', $start['ok'], 'line ' . $start['line_id']);
        $lineId = (int) $start['line_id'];

        $again = $this->builder()->startOrResume('classic-tray');
        $this->check(
            'starting again resumes, does not duplicate',
            (int) $again['line_id'] === $lineId,
            'same line ' . $again['line_id']
        );

        $state = $this->builder()->state($lineId);
        $this->check('capacity read from the box', $state['capacity'] === 6, 'capacity ' . $state['capacity']);
        $this->check('starts empty', $state['slots_used'] === 0, '');
        $this->check('starts incomplete', ! $state['is_complete'], 'min ' . $state['min_slots']);
        $this->check('catalogue offered', $state['catalogue'] !== [], count($state['catalogue']) . ' group(s)');

        CLI::write('  Eligibility', 'white');
        CLI::write('  ' . str_repeat('-', 58), 'dark_gray');

        // Classic Tray allows 5 categories but NOT ceramics or stationery.
        $notebook = model(ProductModel::class)->findVisibleBySlug('cotton-paper-notebook');
        $refused  = $this->builder()->addProduct($lineId, $notebook->id, 1);
        $this->check('product outside allowed categories refused', ! $refused['ok'], $refused['message']);

        $allowed = model(GiftBoxModel::class)->allowedProductIds((int) $state['box']->id);
        $this->check(
            'offer list equals accept list',
            ! in_array($notebook->id, $allowed, true),
            count($allowed) . ' allowed, notebook excluded'
        );

        $soldOut = model(ProductModel::class)->findVisibleBySlug('walnut-halves');
        $out     = $this->builder()->addProduct($lineId, $soldOut->id, 1);
        $this->check('sold-out item refused', ! $out['ok'], $out['message']);

        CLI::write('  Capacity', 'white');
        CLI::write('  ' . str_repeat('-', 58), 'dark_gray');

        $bar = model(ProductModel::class)->findVisibleBySlug('dark-chocolate-72'); // 1 slot
        $this->builder()->addProduct($lineId, $bar->id, 1);
        $state = $this->builder()->state($lineId);
        $this->check('1-slot item uses 1 compartment', $state['slots_used'] === 1, 'used ' . $state['slots_used']);

        $candle = model(ProductModel::class)->findVisibleBySlug('oudh-amber-candle'); // 2 slots
        $this->builder()->addProduct($lineId, $candle->id, 1);
        $state = $this->builder()->state($lineId);
        $this->check('2-slot item uses 2 compartments', $state['slots_used'] === 3, 'used ' . $state['slots_used']);

        // 3 used of 6. A 2-slot item ×2 would need 4 — must not fit.
        $overflow = $this->builder()->addProduct($lineId, $candle->id, 2);
        $this->check('over-capacity add refused', ! $overflow['ok'], $overflow['message']);

        $state = $this->builder()->state($lineId);
        $this->check('refused add changed nothing', $state['slots_used'] === 3, 'still ' . $state['slots_used']);

        // Fill exactly.
        $this->builder()->addProduct($lineId, $bar->id, 2);   // +2 → 5
        $tea = model(ProductModel::class)->findVisibleBySlug('masala-chai-blend');
        $this->builder()->addProduct($lineId, $tea->id, 1);    // +1 → 6
        $state = $this->builder()->state($lineId);
        $this->check('fills to exactly capacity', $state['slots_used'] === 6, 'used ' . $state['slots_used']);
        $this->check('full box reports complete', $state['is_complete'], '');
        $this->check('no free compartments', $state['slots_free'] === 0, '');

        $whenFull = $this->builder()->addProduct($lineId, $bar->id, 1);
        $this->check('cannot add to a full box', ! $whenFull['ok'], $whenFull['message']);

        CLI::write('  Removing and adjusting', 'white');
        CLI::write('  ' . str_repeat('-', 58), 'dark_gray');

        $this->builder()->removeProduct($lineId, $candle->id);
        $state = $this->builder()->state($lineId);
        $this->check('removing frees its compartments', $state['slots_used'] === 4, 'used ' . $state['slots_used']);

        $this->builder()->setProductQuantity($lineId, $bar->id, 1);
        $state = $this->builder()->state($lineId);
        $this->check('reducing quantity works', ($state['chosen'][$bar->id] ?? 0) === 1, '');

        $this->builder()->setProductQuantity($lineId, $tea->id, 0);
        $state = $this->builder()->state($lineId);
        $this->check('quantity 0 removes the item', ! isset($state['chosen'][$tea->id]), '');

        CLI::write('  Personalising', 'white');
        CLI::write('  ' . str_repeat('-', 58), 'dark_gray');

        $limit = (int) $state['box']->gift_message_max_chars;
        $this->builder()->personalise($lineId, 'Aunt Meera', str_repeat('x', $limit + 200), 'No nuts');
        $line = $this->builder()->line($lineId);
        $this->check('recipient saved', $line['gift_recipient'] === 'Aunt Meera', $line['gift_recipient']);
        $this->check(
            'message truncated to the box limit',
            mb_strlen((string) $line['gift_message']) === $limit,
            mb_strlen((string) $line['gift_message']) . ' of ' . $limit
        );
        $this->check('special note saved', $line['special_note'] === 'No nuts', '');

        $this->builder()->personalise($lineId, null, '   ', null);
        $line = $this->builder()->line($lineId);
        $this->check('blank message stores null, not empty string', $line['gift_message'] === null, '');

        CLI::write('  Checkout gate', 'white');
        CLI::write('  ' . str_repeat('-', 58), 'dark_gray');

        // Empty the box: it must now block checkout.
        $this->builder()->clear($lineId);
        $snapshot = service('cart')->snapshot();
        $this->check(
            'empty box blocks checkout',
            $snapshot['blocking'] !== [],
            $snapshot['blocking'][0]['message'] ?? 'no message'
        );

        // Below the minimum (3) but not empty.
        $this->builder()->addProduct($lineId, $bar->id, 1);
        $snapshot = service('cart')->snapshot();
        $this->check(
            'below-minimum box blocks checkout',
            $snapshot['blocking'] !== [],
            $snapshot['blocking'][0]['message'] ?? 'no message'
        );

        $this->builder()->addProduct($lineId, $bar->id, 2);
        $snapshot = service('cart')->snapshot();
        $this->check('at minimum, no longer blocking', $snapshot['blocking'] === [], '3 of 6 filled');

        $boxLine = $snapshot['lines'][0];
        $expected = 550.0 + (320.0 * 3);   // box + 3 bars
        $this->check(
            'box priced as box + contents',
            abs((float) $boxLine['line_total'] - $expected) < 0.01,
            rs_money($boxLine['line_total']) . ' (expected ' . rs_money($expected) . ')'
        );

        CLI::write('  Access control', 'white');
        CLI::write('  ' . str_repeat('-', 58), 'dark_gray');

        $foreign = $this->builder()->state(999999);
        $this->check('unknown line id resolves to null', $foreign === null, 'no IDOR');
        $foreignAdd = $this->builder()->addProduct(999999, $bar->id, 1);
        $this->check('cannot add to a foreign line', ! $foreignAdd['ok'], $foreignAdd['message']);
    }

    private function cleanup(): void
    {
        $cart = service('cart')->current();

        if ($cart !== null) {
            $db = db_connect();
            $ids = array_column(
                $db->table('cart_items')->select('id')->where('cart_id', $cart['id'])->get()->getResultArray(),
                'id'
            );

            if ($ids !== []) {
                $db->table('cart_item_components')->whereIn('cart_item_id', $ids)->delete();
            }

            $db->table('cart_items')->where('cart_id', $cart['id'])->delete();
            $db->table('carts')->where('id', $cart['id'])->delete();
        }

        session()->remove('cart_uuid');
    }
}
